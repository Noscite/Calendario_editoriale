from fastapi import APIRouter, Depends, HTTPException, Query
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
            "scope": "instagram_basic,instagram_content_publish,pages_show_list,pages_read_engagement",
            "response_type": "code"
        }
        auth_url = f"https://www.facebook.com/v18.0/dialog/oauth?{urlencode(params)}"
        
    elif platform == "linkedin":
        params = {
            "response_type": "code",
            "client_id": settings.LINKEDIN_CLIENT_ID,
            "redirect_uri": f"{settings.BASE_URL}/api/social/callback/linkedin",
            "state": state,
            "scope": "openid profile email w_member_social"
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
            token_response = await client.get(
                "https://graph.facebook.com/v18.0/oauth/access_token",
                params={
                    "client_id": settings.META_APP_ID,
                    "client_secret": settings.META_APP_SECRET,
                    "redirect_uri": f"{settings.BASE_URL}/api/social/callback/facebook",
                    "code": code
                }
            )
            token_data = token_response.json()
            
            if "error" in token_data:
                return RedirectResponse(f"{settings.FRONTEND_URL}?social_error={token_data['error']['message']}")
            
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
                return RedirectResponse(f"{settings.FRONTEND_URL}?social_error={token_data.get('error_description', 'token_error')}")
            
            access_token = token_data["access_token"]
            expires_in = token_data.get("expires_in", 5184000)  # 60 giorni default
            
            # Ottieni info profilo
            profile_response = await client.get(
                "https://api.linkedin.com/v2/userinfo",
                headers={"Authorization": f"Bearer {access_token}"}
            )
            profile_data = profile_response.json()
        
        # Salva connessione
        connection = SocialConnection(
            brand_id=state_data["brand_id"],
            platform="linkedin",
            access_token=access_token,
            token_expires_at=datetime.now(timezone.utc).replace(tzinfo=None) + timedelta(seconds=expires_in),
            external_account_id=profile_data.get("sub", ""),
            external_account_name=profile_data.get("name", ""),
            external_account_url=f"https://linkedin.com/in/{profile_data.get('sub', '')}",
            account_type="profile",
            connected_by_user_id=state_data["user_id"]
        )
        
        db.query(SocialConnection).filter(
            SocialConnection.brand_id == state_data["brand_id"],
            SocialConnection.platform == "linkedin"
        ).delete()
        
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
    """Callback OAuth Instagram (via Facebook)"""
    # Instagram usa lo stesso flusso di Facebook
    return await facebook_callback(code, state, error, db)

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
