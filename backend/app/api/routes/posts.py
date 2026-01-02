from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from typing import List, Optional
from pydantic import BaseModel
from datetime import date, datetime

from app.core.database import get_db
from app.core.security import get_current_user
from app.models.user import User
from app.models.brand import Brand
from app.models.project import Project
from app.models.post import Post
from app.schemas.post import PostCreate, PostUpdate, PostResponse
from app.services.claude_service import regenerate_single_post, generate_image_prompt, generate_editorial_plan

class ImageGenerateRequest(BaseModel):
    visual_suggestion: Optional[str] = None
from app.services.url_analyzer import get_brand_context_from_urls
import asyncio
from app.services.openai_service import OpenAIService

router = APIRouter()

# === SCHEMAS ===

class RegenerateRequest(BaseModel):
    prompt: Optional[str] = "Rigenera questo post mantenendo lo stesso messaggio ma migliorando engagement e chiarezza"

class ImageResponse(BaseModel):
    image_url: str

class ManualPostCreate(BaseModel):
    project_id: int
    platform: str
    scheduled_date: date
    scheduled_time: Optional[str] = "09:00"
    content: str
    hashtags: Optional[List[str]] = []
    pillar: Optional[str] = ""
    post_type: Optional[str] = "educational"
    visual_suggestion: Optional[str] = ""
    cta: Optional[str] = ""

class AIPostGenerateRequest(BaseModel):
    project_id: int
    platform: str
    start_date: date
    end_date: date
    num_posts: int = 3
    brief: str  # Es: "Campagna sul nuovo partner X", "Promozione Black Friday"
    pillar: Optional[str] = ""

class BatchDeleteRequest(BaseModel):
    post_ids: List[int]

class BatchReplaceRequest(BaseModel):
    post_ids: List[int]
    brief: str  # Es: "Il partner X non c'è più, parla del nuovo partner Y"

# === ENDPOINTS ESISTENTI ===

@router.get("/project/{project_id}", response_model=List[PostResponse])
def get_posts_by_project(
    project_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    project = db.query(Project).join(Brand).filter(
        Project.id == project_id,
        Brand.organization_id == current_user.organization_id
    ).first()
    if not project:
        raise HTTPException(status_code=404, detail="Progetto non trovato")
    return db.query(Post).filter(Post.project_id == project_id).order_by(Post.scheduled_date, Post.scheduled_time).all()

@router.get("/{post_id}", response_model=PostResponse)
def get_post(
    post_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    post = db.query(Post).join(Project).join(Brand).filter(
        Post.id == post_id,
        Brand.organization_id == current_user.organization_id
    ).first()
    if not post:
        raise HTTPException(status_code=404, detail="Post non trovato")
    return post

@router.put("/{post_id}", response_model=PostResponse)
def update_post(
    post_id: int,
    post_data: PostUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    post = db.query(Post).join(Project).join(Brand).filter(
        Post.id == post_id,
        Brand.organization_id == current_user.organization_id
    ).first()
    if not post:
        raise HTTPException(status_code=404, detail="Post non trovato")
    for key, value in post_data.model_dump(exclude_unset=True).items():
        setattr(post, key, value)
    db.commit()
    db.refresh(post)
    return post

@router.patch("/{post_id}", response_model=PostResponse)
def patch_post(
    post_id: int,
    post_data: PostUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    return update_post(post_id, post_data, db, current_user)

@router.delete("/{post_id}")
def delete_post(
    post_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    post = db.query(Post).join(Project).join(Brand).filter(
        Post.id == post_id,
        Brand.organization_id == current_user.organization_id
    ).first()
    if not post:
        raise HTTPException(status_code=404, detail="Post non trovato")
    db.delete(post)
    db.commit()
    return {"message": "Post eliminato"}

# === NUOVI ENDPOINTS ===

@router.post("/manual", response_model=PostResponse)
def create_manual_post(
    post_data: ManualPostCreate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Crea un post manualmente"""
    project = db.query(Project).join(Brand).filter(
        Project.id == post_data.project_id,
        Brand.organization_id == current_user.organization_id
    ).first()
    if not project:
        raise HTTPException(status_code=404, detail="Progetto non trovato")
    
    post = Post(
        project_id=post_data.project_id,
        platform=post_data.platform,
        scheduled_date=post_data.scheduled_date,
        scheduled_time=post_data.scheduled_time,
        content=post_data.content,
        hashtags=post_data.hashtags,
        pillar=post_data.pillar,
        post_type=post_data.post_type,
        visual_suggestion=post_data.visual_suggestion,
        cta=post_data.cta,
        status="draft"
    )
    db.add(post)
    db.commit()
    db.refresh(post)
    return post

@router.post("/generate-ai", response_model=List[PostResponse])
async def generate_ai_posts(
    request: AIPostGenerateRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Genera post con AI per un periodo/campagna specifica"""
    project = db.query(Project).join(Brand).filter(
        Project.id == request.project_id,
        Brand.organization_id == current_user.organization_id
    ).first()
    if not project:
        raise HTTPException(status_code=404, detail="Progetto non trovato")
    
    brand = db.query(Brand).filter(Brand.id == project.brand_id).first()
    
    # === ANALISI URL DI RIFERIMENTO ===
    brand_context_from_urls = ""
    reference_urls = project.reference_urls or []
    if reference_urls:
        print(f"[GENERATE-AI] Analyzing {len(reference_urls)} reference URLs...")
        try:
            brand_context_from_urls = await get_brand_context_from_urls(
                urls=reference_urls,
                brand_name=brand.name if brand else project.name
            )
            print(f"[GENERATE-AI] URL context generated: {len(brand_context_from_urls)} chars")
        except Exception as e:
            print(f"[GENERATE-AI] URL analysis error: {e}")
    
    # Calcola posts_per_week per avere il numero richiesto nel periodo
    from datetime import timedelta
    days = (request.end_date - request.start_date).days + 1
    weeks = max(1, days / 7)
    posts_per_week_calc = max(1, int(request.num_posts / weeks))
    
    # Combina content pillars del progetto con pillar richiesto
    themes = [request.pillar] if request.pillar else (project.content_pillars or project.themes or [])
    
    # Costruisci brief arricchito
    enriched_brief = request.brief
    if project.target_audience:
        enriched_brief += f"\n\nTARGET AUDIENCE: {project.target_audience}"
    if project.competitors:
        enriched_brief += f"\n\nCOMPETITOR DA CONSIDERARE: {', '.join(project.competitors)}"
    
    try:
        posts_data = generate_editorial_plan(
            brand_name=brand.name if brand else "",
            brand_sector=brand.sector or "",
            tone_of_voice=brand.tone_of_voice or "",
            brand_values=brand.brand_values or "",
            start_date=str(request.start_date),
            end_date=str(request.end_date),
            platforms=[request.platform],
            posts_per_week={request.platform: posts_per_week_calc},
            brief=enriched_brief,
            themes=themes,
            custom_prompt=f"Genera esattamente {request.num_posts} post. {request.brief}",
            brand_style_guide=brand.style_guide if brand else "",
            urls_content=brand_context_from_urls
        )
        
        # Limita al numero richiesto
        posts_data = posts_data[:request.num_posts]
        
        created_posts = []
        for post_data in posts_data:
            post = Post(
                project_id=request.project_id,
                platform=post_data.get("platform", request.platform),
                scheduled_date=post_data.get("scheduled_date"),
                scheduled_time=post_data.get("scheduled_time", "09:00"),
                content=post_data.get("content", ""),
                hashtags=post_data.get("hashtags", []),
                pillar=post_data.get("pillar", request.pillar),
                post_type=post_data.get("post_type", ""),
                visual_suggestion=post_data.get("visual_suggestion", ""),
                cta=post_data.get("cta", ""),
                status="draft"
            )
            db.add(post)
            db.flush()
            created_posts.append(post)
        
        db.commit()
        for p in created_posts:
            db.refresh(p)
        
        return created_posts
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Errore generazione AI: {str(e)}")

@router.post("/batch-delete")
def batch_delete_posts(
    request: BatchDeleteRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Elimina più post contemporaneamente"""
    deleted_count = 0
    for post_id in request.post_ids:
        post = db.query(Post).join(Project).join(Brand).filter(
            Post.id == post_id,
            Brand.organization_id == current_user.organization_id
        ).first()
        if post:
            db.delete(post)
            deleted_count += 1
    db.commit()
    return {"message": f"Eliminati {deleted_count} post", "deleted_count": deleted_count}

@router.post("/batch-replace", response_model=List[PostResponse])
def batch_replace_posts(
    request: BatchReplaceRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Sostituisce più post con nuovi generati da AI"""
    # Carica i post da sostituire
    posts_to_replace = []
    for post_id in request.post_ids:
        post = db.query(Post).join(Project).join(Brand).filter(
            Post.id == post_id,
            Brand.organization_id == current_user.organization_id
        ).first()
        if post:
            posts_to_replace.append(post)
    
    if not posts_to_replace:
        raise HTTPException(status_code=404, detail="Nessun post trovato")
    
    # Prendi info dal primo post
    first_post = posts_to_replace[0]
    project = first_post.project
    brand = db.query(Brand).filter(Brand.id == project.brand_id).first()
    
    # Determina periodo dai post selezionati
    dates = [p.scheduled_date for p in posts_to_replace]
    start_date = min(dates)
    end_date = max(dates)
    platforms = list(set(p.platform for p in posts_to_replace))
    
    try:
        # Genera nuovi post
        posts_data = generate_editorial_plan(
            brand_name=brand.name if brand else "",
            brand_sector=brand.sector or "",
            tone_of_voice=brand.tone_of_voice or "",
            brand_values=brand.brand_values or "",
            start_date=str(start_date),
            end_date=str(end_date),
            platforms=platforms,
            posts_per_week={p: 7 for p in platforms},  # Alta frequenza per coprire il periodo
            brief=request.brief,
            themes=project.themes or [],
            custom_prompt=f"Genera esattamente {len(posts_to_replace)} post. CONTESTO IMPORTANTE: {request.brief}",
            brand_style_guide=brand.style_guide if brand else ""
        )
        
        posts_data = posts_data[:len(posts_to_replace)]
        
        # Elimina vecchi post
        for post in posts_to_replace:
            db.delete(post)
        
        # Crea nuovi post
        new_posts = []
        for i, post_data in enumerate(posts_data):
            # Usa la data del post originale se disponibile
            original_date = posts_to_replace[i].scheduled_date if i < len(posts_to_replace) else post_data.get("scheduled_date")
            original_time = posts_to_replace[i].scheduled_time if i < len(posts_to_replace) else post_data.get("scheduled_time", "09:00")
            original_platform = posts_to_replace[i].platform if i < len(posts_to_replace) else post_data.get("platform")
            
            post = Post(
                project_id=project.id,
                platform=original_platform,
                scheduled_date=original_date,
                scheduled_time=original_time,
                content=post_data.get("content", ""),
                hashtags=post_data.get("hashtags", []),
                pillar=post_data.get("pillar", ""),
                post_type=post_data.get("post_type", ""),
                visual_suggestion=post_data.get("visual_suggestion", ""),
                cta=post_data.get("cta", ""),
                status="draft"
            )
            db.add(post)
            db.flush()
            new_posts.append(post)
        
        db.commit()
        for p in new_posts:
            db.refresh(p)
        
        return new_posts
        
    except Exception as e:
        db.rollback()
        raise HTTPException(status_code=500, detail=f"Errore sostituzione: {str(e)}")

@router.post("/{post_id}/regenerate", response_model=PostResponse)
def regenerate_post(
    post_id: int,
    request: RegenerateRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Rigenera il contenuto di un singolo post con AI"""
    post = db.query(Post).join(Project).join(Brand).filter(
        Post.id == post_id,
        Brand.organization_id == current_user.organization_id
    ).first()
    if not post:
        raise HTTPException(status_code=404, detail="Post non trovato")
    
    project = post.project
    brand = db.query(Brand).filter(Brand.id == project.brand_id).first()
    
    try:
        result = regenerate_single_post(
            post_content=post.content or "",
            platform=post.platform,
            pillar=post.pillar or "",
            user_prompt=request.prompt,
            brand_context=f"{brand.name} - {brand.sector}" if brand else "",
            tone_of_voice=brand.tone_of_voice if brand else "",
            brand_style_guide=brand.style_guide if brand else ""
        )
        
        if result.get("content"):
            post.content = result["content"]
        if result.get("hashtags"):
            post.hashtags = result["hashtags"]
        if result.get("visual_suggestion"):
            post.visual_suggestion = result["visual_suggestion"]
        if result.get("cta"):
            post.cta = result["cta"]
        
        db.commit()
        db.refresh(post)
        return post
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Errore rigenerazione: {str(e)}")

@router.post("/{post_id}/generate-image", response_model=ImageResponse)
async def generate_post_image(
    post_id: int,
    request: ImageGenerateRequest = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Genera immagine con DALL-E per un post"""
    post = db.query(Post).join(Project).join(Brand).filter(
        Post.id == post_id,
        Brand.organization_id == current_user.organization_id
    ).first()
    if not post:
        raise HTTPException(status_code=404, detail="Post non trovato")
    
    # Usa visual_suggestion dal request body se fornito, altrimenti dal post
    visual_prompt = (request.visual_suggestion if request and request.visual_suggestion else post.visual_suggestion)
    
    if not visual_prompt:
        raise HTTPException(status_code=400, detail="Nessun suggerimento visual disponibile. Inserisci un prompt per generare l'immagine.")
    
    # Aggiorna il post con il nuovo visual_suggestion se fornito
    if request and request.visual_suggestion:
        post.visual_suggestion = request.visual_suggestion
    
    project = post.project
    brand = db.query(Brand).filter(Brand.id == project.brand_id).first()
    
    try:
        detailed_prompt = generate_image_prompt(
            post_content=post.content or "",
            platform=post.platform,
            pillar=post.pillar or "",
            brand_name=brand.name if brand else "",
            brand_sector=brand.sector if brand else "",
            brand_colors=brand.colors if brand else ""
        )
        
        post.image_prompt = detailed_prompt
        
        # Dimensioni ottimali per piattaforma
        platform_sizes = {
            "instagram": "1024x1024",      # Feed quadrato
            "instagram_story": "1024x1792", # Stories/Reels verticale
            "linkedin": "1792x1024",        # Landscape professionale
            "facebook": "1792x1024",        # Landscape engagement
            "google_business": "1024x1024", # Quadrato per local
            "twitter": "1792x1024",         # Landscape
            "blog": "1792x1024"             # Header landscape
        }
        size = platform_sizes.get(post.platform, "1024x1024")
        openai_service = OpenAIService()
        image_url = await openai_service.generate_image(prompt=detailed_prompt, size=size)
        
        post.image_url = image_url
        db.commit()
        db.refresh(post)
        
        return {"image_url": image_url}
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Errore generazione immagine: {str(e)}")


# === SCHEDULING ENDPOINTS ===

class ScheduleRequest(BaseModel):
    scheduled_for: datetime
    platforms: List[str]

class ScheduleResponse(BaseModel):
    message: str
    scheduled_for: datetime
    platforms: List[str]

@router.post("/{post_id}/schedule", response_model=ScheduleResponse)
async def schedule_post(
    post_id: int,
    request: ScheduleRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Schedula un post per la pubblicazione automatica"""
    from app.models.social_connection import SocialConnection, PostPublication
    
    # Verifica post
    post = db.query(Post).join(Project).join(Brand).filter(
        Post.id == post_id,
        Brand.organization_id == current_user.organization_id
    ).first()
    
    if not post:
        raise HTTPException(status_code=404, detail="Post non trovato")
    
    project = post.project
    brand = db.query(Brand).filter(Brand.id == project.brand_id).first()
    
    scheduled_platforms = []
    
    for platform in request.platforms:
        # Verifica connessione attiva per la piattaforma
        connection = db.query(SocialConnection).filter(
            SocialConnection.brand_id == brand.id,
            SocialConnection.platform == platform,
            SocialConnection.is_active == True
        ).first()
        
        if not connection:
            raise HTTPException(
                status_code=400, 
                detail=f"Nessuna connessione attiva per {platform}. Connetti prima l'account nelle impostazioni."
            )
        
        # Verifica se esiste già una pubblicazione per questo post/connessione
        existing = db.query(PostPublication).filter(
            PostPublication.post_id == post_id,
            PostPublication.social_connection_id == connection.id
        ).first()
        
        if existing:
            # Aggiorna schedulazione esistente
            existing.scheduled_for = request.scheduled_for
            existing.status = "scheduled"
            existing.error_message = None
            existing.retry_count = 0
        else:
            # Crea nuova pubblicazione
            publication = PostPublication(
                post_id=post_id,
                social_connection_id=connection.id,
                status="scheduled",
                scheduled_for=request.scheduled_for
            )
            db.add(publication)
        
        scheduled_platforms.append(platform)
    
    # Aggiorna stato post
    post.publication_status = "scheduled"
    db.commit()
    
    return {
        "message": "Post pianificato con successo",
        "scheduled_for": request.scheduled_for,
        "platforms": scheduled_platforms
    }

@router.delete("/{post_id}/schedule")
async def cancel_schedule(
    post_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Annulla la schedulazione di un post"""
    from app.models.social_connection import PostPublication
    
    post = db.query(Post).join(Project).join(Brand).filter(
        Post.id == post_id,
        Brand.organization_id == current_user.organization_id
    ).first()
    
    if not post:
        raise HTTPException(status_code=404, detail="Post non trovato")
    
    # Rimuovi tutte le pubblicazioni schedulate
    db.query(PostPublication).filter(
        PostPublication.post_id == post_id,
        PostPublication.status == "scheduled"
    ).delete()
    
    post.publication_status = "draft"
    db.commit()
    
    return {"message": "Schedulazione annullata"}

@router.get("/{post_id}/schedule-status")
async def get_schedule_status(
    post_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Ottieni stato schedulazione di un post"""
    from app.models.social_connection import SocialConnection, PostPublication
    
    post = db.query(Post).join(Project).join(Brand).filter(
        Post.id == post_id,
        Brand.organization_id == current_user.organization_id
    ).first()
    
    if not post:
        raise HTTPException(status_code=404, detail="Post non trovato")
    
    publications = db.query(PostPublication).filter(
        PostPublication.post_id == post_id
    ).all()
    
    result = []
    for pub in publications:
        connection = db.query(SocialConnection).filter(
            SocialConnection.id == pub.social_connection_id
        ).first()
        
        result.append({
            "platform": connection.platform if connection else "unknown",
            "status": pub.status,
            "scheduled_for": pub.scheduled_for,
            "published_at": pub.published_at,
            "external_post_url": pub.external_post_url,
            "error_message": pub.error_message
        })
    
    return {
        "post_id": post_id,
        "publication_status": post.publication_status,
        "publications": result
    }
