from fastapi import APIRouter, Depends, HTTPException, Query
import logging
logger = logging.getLogger(__name__)
from datetime import datetime, timezone, timedelta
from fastapi.responses import RedirectResponse
from sqlalchemy.orm import Session
from typing import List, Optional
from pydantic import BaseModel
import httpx
import secrets
from urllib.parse import urlencode

from app.core.database import get_db
from app.core.config import settings
from app.models.user import User
from app.models.brand import Brand
from app.models.social_connection import SocialConnection, PostPublication
from app.api.routes.auth import get_current_user

router = APIRouter()

# === SCHEMAS ===

class SocialConnectionOut(BaseModel):
    id: int
    platform: str
    external_account_id: str
    external_account_name: Optional[str]
    external_account_url: Optional[str]
    account_type: Optional[str]
    is_active: bool
    connected_at: datetime
    
    class Config:
        from_attributes = True

class ConnectSocialRequest(BaseModel):
    brand_id: int
    platform: str

class DisconnectRequest(BaseModel):
    connection_id: int

# Store temporaneo per OAuth state (in produzione usare Redis)
oauth_states = {}

# === ENDPOINTS ===

@router.get("/connections/{brand_id}", response_model=List[SocialConnectionOut])
async def get_brand_connections(
    brand_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Lista connessioni social di un brand"""
    # Verifica che il brand appartenga all'organizzazione dell'utente
    brand = db.query(Brand).filter(
        Brand.id == brand_id,
        Brand.organization_id == current_user.organization_id
    ).first()
    
    if not brand:
        raise HTTPException(status_code=404, detail="Brand non trovato")
    
    connections = db.query(SocialConnection).filter(
        SocialConnection.brand_id == brand_id,
        SocialConnection.is_active == True
    ).all()
    
    return connections

@router.get("/authorize/{platform}")
async def authorize_social(
    platform: str,
    brand_id: int = Query(...),
    token: str = Query(None),
    db: Session = Depends(get_db)
):
    """Inizia il flusso OAuth per una piattaforma"""
    
    # Autentica da token query param o header
    if token:
        from jose import jwt
        from app.core.config import settings
        try:
            payload = jwt.decode(token, settings.SECRET_KEY, algorithms=["HS256"])
            user_id = payload.get("sub")
            current_user = db.query(User).filter(User.email == user_id).first()
        except:
            raise HTTPException(status_code=401, detail="Token non valido")
    else:
        raise HTTPException(status_code=401, detail="Token richiesto")

    # Verifica brand
    brand = db.query(Brand).filter(
        Brand.id == brand_id,
        Brand.organization_id == current_user.organization_id
    ).first()
    
    if not brand:
        raise HTTPException(status_code=404, detail="Brand non trovato")
    
    # Genera state token
    state = secrets.token_urlsafe(32)
    oauth_states[state] = {
        "brand_id": brand_id,
        "user_id": current_user.id,
        "platform": platform,
        "created_at": datetime.now(timezone.utc)
    }
    
    # Costruisci URL di autorizzazione
    if platform == "facebook":
        params = {
            "client_id": settings.META_APP_ID,
            "redirect_uri": f"{settings.BASE_URL}/api/social/callback/facebook",
            "state": state,
            "scope": "pages_show_list,pages_read_engagement,pages_manage_posts,public_profile",
            "response_type": "code"
        }
        auth_url = f"https://www.facebook.com/v18.0/dialog/oauth?{urlencode(params)}"
        
    elif platform == "instagram":
        params = {
            "client_id": settings.META_APP_ID,
            "redirect_uri": f"{settings.BASE_URL}/api/social/callback/instagram",
            "state": state,
            "scope": "instagram_basic,instagram_content_publish,pages_show_list,pages_read_engagement,pages_manage_posts,business_management",
            "response_type": "code"
        }
        auth_url = f"https://www.facebook.com/v18.0/dialog/oauth?{urlencode(params)}"
        
    elif platform == "linkedin":
        params = {
            "response_type": "code",
            "client_id": settings.LINKEDIN_CLIENT_ID,
            "redirect_uri": f"{settings.BASE_URL}/api/social/callback/linkedin",
            "state": state,
            "scope": "openid profile email w_member_social w_organization_social"
        }
        auth_url = f"https://www.linkedin.com/oauth/v2/authorization?{urlencode(params)}"
        
    elif platform == "google_business":
        params = {
            "client_id": settings.GOOGLE_CLIENT_ID,
            "redirect_uri": f"{settings.BASE_URL}/api/social/callback/google",
            "state": state,
            "scope": "https://www.googleapis.com/auth/business.manage",
            "response_type": "code",
            "access_type": "offline",
            "prompt": "consent"
        }
        auth_url = f"https://accounts.google.com/o/oauth2/v2/auth?{urlencode(params)}"
    else:
        raise HTTPException(status_code=400, detail="Piattaforma non supportata")
    
    return RedirectResponse(url=auth_url)

@router.get("/callback/facebook")
async def facebook_callback(
    code: str = None,
    state: str = None,
    error: str = None,
    db: Session = Depends(get_db)
):
    """Callback OAuth Facebook"""
    if error or not code or not state:
        return RedirectResponse(f"{settings.FRONTEND_URL}?social_error={error or 'missing_code'}")
    
    # Verifica state
    state_data = oauth_states.pop(state, None)
    if not state_data:
        return RedirectResponse(f"{settings.FRONTEND_URL}?social_error=invalid_state")
    
    try:
        # Scambia code per access_token
        async with httpx.AsyncClient() as client:
            token_params = {
                    "client_id": settings.META_APP_ID,
                    "client_secret": settings.META_APP_SECRET,
                    "redirect_uri": f"{settings.BASE_URL}/api/social/callback/facebook",
                    "code": code
                }
            logger.info(f"Facebook token request params: {token_params}")
            token_response = await client.get(
                "https://graph.facebook.com/v18.0/oauth/access_token",
                params=token_params
            )
            logger.info(f"Facebook response status: {token_response.status_code}")
            logger.info(f"Facebook response body: {token_response.text}")
            token_data = token_response.json()
            logger.info(f"Facebook token response: {token_response.status_code} - {token_data}")
            
            if "error" in token_data:
                logger.error(f"Facebook token error: {token_data}")
                return RedirectResponse(f"{settings.FRONTEND_URL}?social_error={token_data['error'].get('message', 'unknown')}")
            
            access_token = token_data["access_token"]
            
            # Ottieni info utente e pagine
            me_response = await client.get(
                "https://graph.facebook.com/v18.0/me",
                params={"access_token": access_token, "fields": "id,name"}
            )
            me_data = me_response.json()
            
            # Ottieni pagine gestite
            pages_response = await client.get(
                "https://graph.facebook.com/v18.0/me/accounts",
                params={"access_token": access_token}
            )
            pages_data = pages_response.json()
            
            # Per ora salviamo la prima pagina (in futuro: far scegliere all'utente)
            if pages_data.get("data"):
                page = pages_data["data"][0]
                page_token = page["access_token"]
                page_id = page["id"]
                page_name = page["name"]
            else:
                # Nessuna pagina, usa profilo personale
                page_token = access_token
                page_id = me_data["id"]
                page_name = me_data["name"]
        
        # Salva connessione
        connection = SocialConnection(
            brand_id=state_data["brand_id"],
            platform="facebook",
            access_token=page_token,
            external_account_id=page_id,
            external_account_name=page_name,
            external_account_url=f"https://facebook.com/{page_id}",
            account_type="page" if pages_data.get("data") else "profile",
            connected_by_user_id=state_data["user_id"]
        )
        
        # Rimuovi vecchia connessione se esiste
        db.query(SocialConnection).filter(
            SocialConnection.brand_id == state_data["brand_id"],
            SocialConnection.platform == "facebook"
        ).delete()
        
        db.add(connection)
        db.commit()
        
        return RedirectResponse(f"{settings.FRONTEND_URL}/brand/{state_data['brand_id']}?social_connected=facebook")
        
    except Exception as e:
        return RedirectResponse(f"{settings.FRONTEND_URL}?social_error={str(e)}")

@router.get("/callback/linkedin")
async def linkedin_callback(
    code: str = None,
    state: str = None,
    error: str = None,
    db: Session = Depends(get_db)
):
    """Callback OAuth LinkedIn"""
    if error or not code or not state:
        return RedirectResponse(f"{settings.FRONTEND_URL}?social_error={error or 'missing_code'}")
    
    state_data = oauth_states.pop(state, None)
    if not state_data:
        return RedirectResponse(f"{settings.FRONTEND_URL}?social_error=invalid_state")
    
    try:
        async with httpx.AsyncClient() as client:
            # Scambia code per token
            token_response = await client.post(
                "https://www.linkedin.com/oauth/v2/accessToken",
                data={
                    "grant_type": "authorization_code",
                    "code": code,
                    "client_id": settings.LINKEDIN_CLIENT_ID,
                    "client_secret": settings.LINKEDIN_CLIENT_SECRET,
                    "redirect_uri": f"{settings.BASE_URL}/api/social/callback/linkedin"
                },
                headers={"Content-Type": "application/x-www-form-urlencoded"}
            )
            token_data = token_response.json()
            
            if "error" in token_data:
                logger.error(f"LinkedIn token error: {token_data}")
                return RedirectResponse(f"{settings.FRONTEND_URL}?social_error={token_data.get('error_description', 'token_error')}")
            
            access_token = token_data["access_token"]
            expires_in = token_data.get("expires_in", 5184000)  # 60 giorni default
            
            # Ottieni info profilo
            profile_response = await client.get(
                "https://api.linkedin.com/v2/userinfo",
                headers={"Authorization": f"Bearer {access_token}"}
            )
            profile_data = profile_response.json()
            logger.info(f"LinkedIn profile: {profile_data.get('name')}")
            
            # Ottieni le organizzazioni (pagine aziendali) di cui l'utente è admin
            orgs_response = await client.get(
                "https://api.linkedin.com/v2/organizationAcls?q=roleAssignee&role=ADMINISTRATOR&projection=(elements*(organization~(id,localizedName,vanityName)))",
                headers={
                    "Authorization": f"Bearer {access_token}",
                    "X-Restli-Protocol-Version": "2.0.0"
                }
            )
            orgs_data = orgs_response.json()
            logger.info(f"LinkedIn organizations response: {orgs_data}")
            
            organizations = []
            if orgs_data.get("elements"):
                for element in orgs_data["elements"]:
                    org = element.get("organization~", {})
                    if org:
                        organizations.append({
                            "id": org.get("id"),
                            "name": org.get("localizedName"),
                            "vanity_name": org.get("vanityName")
                        })
            
            logger.info(f"LinkedIn found {len(organizations)} organizations")
        
        # Elimina vecchie connessioni LinkedIn per questo brand
        db.query(SocialConnection).filter(
            SocialConnection.brand_id == state_data["brand_id"],
            SocialConnection.platform == "linkedin"
        ).delete()
        
        # Salva connessione profilo personale
        profile_connection = SocialConnection(
            brand_id=state_data["brand_id"],
            platform="linkedin",
            access_token=access_token,
            token_expires_at=datetime.now(timezone.utc).replace(tzinfo=None) + timedelta(seconds=expires_in),
            external_account_id=profile_data.get("sub", ""),
            external_account_name=f"{profile_data.get('name', '')} (Profilo)",
            external_account_url=f"https://linkedin.com/in/{profile_data.get('sub', '')}",
            account_type="profile",
            connected_by_user_id=state_data["user_id"]
        )
        db.add(profile_connection)
        
        # Salva connessioni per ogni organizzazione
        for org in organizations:
            org_connection = SocialConnection(
                brand_id=state_data["brand_id"],
                platform="linkedin",
                access_token=access_token,
                token_expires_at=datetime.now(timezone.utc).replace(tzinfo=None) + timedelta(seconds=expires_in),
                external_account_id=str(org["id"]),
                external_account_name=f"{org['name']} (Pagina)",
                external_account_url=f"https://linkedin.com/company/{org.get('vanity_name', org['id'])}",
                account_type="organization",
                connected_by_user_id=state_data["user_id"]
            )
            db.add(org_connection)
        
        db.commit()
        
        logger.info(f"LinkedIn connected: profile + {len(organizations)} organizations for brand {state_data['brand_id']}")
        return RedirectResponse(f"{settings.FRONTEND_URL}/?social_connected=linkedin")
            
    except Exception as e:
        logger.error(f"LinkedIn callback error: {e}")
        return RedirectResponse(f"{settings.FRONTEND_URL}?social_error=callback_failed")


# Placeholder per mantenere la struttura - questo verrà rimosso dal replace

        
        db.add(connection)
        db.commit()
        
        return RedirectResponse(f"{settings.FRONTEND_URL}/brand/{state_data['brand_id']}?social_connected=linkedin")
        
    except Exception as e:
        return RedirectResponse(f"{settings.FRONTEND_URL}?social_error={str(e)}")

@router.get("/callback/google")
async def google_callback(
    code: str = None,
    state: str = None,
    error: str = None,
    db: Session = Depends(get_db)
):
    """Callback OAuth Google Business"""
    if error or not code or not state:
        return RedirectResponse(f"{settings.FRONTEND_URL}?social_error={error or 'missing_code'}")
    
    state_data = oauth_states.pop(state, None)
    if not state_data:
        return RedirectResponse(f"{settings.FRONTEND_URL}?social_error=invalid_state")
    
    try:
        async with httpx.AsyncClient() as client:
            token_response = await client.post(
                "https://oauth2.googleapis.com/token",
                data={
                    "code": code,
                    "client_id": settings.GOOGLE_CLIENT_ID,
                    "client_secret": settings.GOOGLE_CLIENT_SECRET,
                    "redirect_uri": f"{settings.BASE_URL}/api/social/callback/google",
                    "grant_type": "authorization_code"
                }
            )
            token_data = token_response.json()
            
            if "error" in token_data:
                return RedirectResponse(f"{settings.FRONTEND_URL}?social_error={token_data.get('error_description', 'token_error')}")
            
            access_token = token_data["access_token"]
            refresh_token = token_data.get("refresh_token")
            expires_in = token_data.get("expires_in", 3600)
            
            # Ottieni info account
            userinfo_response = await client.get(
                "https://www.googleapis.com/oauth2/v2/userinfo",
                headers={"Authorization": f"Bearer {access_token}"}
            )
            userinfo = userinfo_response.json()
        
        connection = SocialConnection(
            brand_id=state_data["brand_id"],
            platform="google_business",
            access_token=access_token,
            refresh_token=refresh_token,
            token_expires_at=datetime.now(timezone.utc).replace(tzinfo=None) + timedelta(seconds=expires_in),
            external_account_id=userinfo.get("id", ""),
            external_account_name=userinfo.get("name", userinfo.get("email", "")),
            account_type="business",
            connected_by_user_id=state_data["user_id"]
        )
        
        db.query(SocialConnection).filter(
            SocialConnection.brand_id == state_data["brand_id"],
            SocialConnection.platform == "google_business"
        ).delete()
        
        db.add(connection)
        db.commit()
        
        return RedirectResponse(f"{settings.FRONTEND_URL}/brand/{state_data['brand_id']}?social_connected=google_business")
        
    except Exception as e:
        return RedirectResponse(f"{settings.FRONTEND_URL}?social_error={str(e)}")

@router.get("/callback/instagram")
async def instagram_callback(
    code: str = None,
    state: str = None,
    error: str = None,
    db: Session = Depends(get_db)
):
    """Callback OAuth Instagram (via Facebook Graph API)"""
    if error or not code or not state:
        return RedirectResponse(f"{settings.FRONTEND_URL}?social_error={error or 'missing_code'}")
    
    # Verifica state
    state_data = oauth_states.pop(state, None)
    if not state_data:
        return RedirectResponse(f"{settings.FRONTEND_URL}?social_error=invalid_state")
    
    try:
        async with httpx.AsyncClient() as client:
            # Scambia code per access_token - USA IL REDIRECT_URI DI INSTAGRAM!
            token_response = await client.get(
                "https://graph.facebook.com/v18.0/oauth/access_token",
                params={
                    "client_id": settings.META_APP_ID,
                    "client_secret": settings.META_APP_SECRET,
                    "redirect_uri": f"{settings.BASE_URL}/api/social/callback/instagram",
                    "code": code
                }
            )
            token_data = token_response.json()
            
            if "error" in token_data:
                logger.error(f"Instagram token error: {token_data}")
                return RedirectResponse(f"{settings.FRONTEND_URL}?social_error={token_data['error'].get('message', 'token_error')}")
            
            access_token = token_data["access_token"]
            
            # Ottieni pagine Facebook collegate
            pages_response = await client.get(
                "https://graph.facebook.com/v18.0/me/accounts",
                params={"access_token": access_token}
            )
            pages_data = pages_response.json()
            logger.info(f"Instagram - Pages found: {len(pages_data.get('data', []))}")
            
            if not pages_data.get("data"):
                logger.error("Instagram - No Facebook pages found")
                return RedirectResponse(f"{settings.FRONTEND_URL}?social_error=no_pages_found")
            
            # Per ogni pagina, cerca account Instagram collegato
            instagram_account = None
            page_access_token = None
            
            for page in pages_data["data"]:
                logger.info(f"Instagram - Checking page: {page.get('name', 'unknown')} (ID: {page['id']})")
                ig_response = await client.get(
                    f"https://graph.facebook.com/v18.0/{page['id']}",
                    params={
                        "access_token": page["access_token"],
                        "fields": "instagram_business_account"
                    }
                )
                ig_data = ig_response.json()
                logger.info(f"Instagram - Page IG data: {ig_data}")
                
                if ig_data.get("instagram_business_account"):
                    instagram_account = ig_data["instagram_business_account"]["id"]
                    page_access_token = page["access_token"]
                    logger.info(f"Instagram - Found IG business account: {instagram_account}")
                    break
            
            if not instagram_account:
                logger.error("Instagram - No Instagram business account linked to any Facebook page")
                return RedirectResponse(f"{settings.FRONTEND_URL}?social_error=no_instagram_business_account")
            
            # Ottieni info account Instagram
            ig_info_response = await client.get(
                f"https://graph.facebook.com/v18.0/{instagram_account}",
                params={
                    "access_token": page_access_token,
                    "fields": "id,username"
                }
            )
            ig_info = ig_info_response.json()
            
            # Salva connessione
            brand_id = state_data["brand_id"]
            user_id = state_data["user_id"]
            
            # Rimuovi connessioni Instagram esistenti per questo brand
            db.query(SocialConnection).filter(
                SocialConnection.brand_id == brand_id,
                SocialConnection.platform == "instagram"
            ).delete()
            
            connection = SocialConnection(
                brand_id=brand_id,
                connected_by_user_id=user_id,
                platform="instagram",
                external_account_id=instagram_account,
                access_token=page_access_token,
                account_type="business",
                is_active=True
            )
            db.add(connection)
            db.commit()
            
            logger.info(f"Instagram connected: {ig_info.get('username')} for brand {brand_id}")
            return RedirectResponse(f"{settings.FRONTEND_URL}/?social_connected=instagram")
            
    except Exception as e:
        logger.error(f"Instagram callback error: {e}")
        return RedirectResponse(f"{settings.FRONTEND_URL}?social_error=callback_failed")

@router.delete("/disconnect/{connection_id}")
async def disconnect_social(
    connection_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Disconnetti un account social"""
    connection = db.query(SocialConnection).join(Brand).filter(
        SocialConnection.id == connection_id,
        Brand.organization_id == current_user.organization_id
    ).first()
    
    if not connection:
        raise HTTPException(status_code=404, detail="Connessione non trovata")
    
    connection.is_active = False
    db.commit()
    
    return {"message": "Account disconnesso"}
