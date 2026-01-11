from datetime import date
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from pydantic import BaseModel
from typing import Optional
import threading
import logging

logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(name)s - %(levelname)s - %(message)s")
logger = logging.getLogger(__name__)

import asyncio

from app.core.database import get_db, SessionLocal
from app.models.project import Project, ProjectStatus
from app.models.post import Post
from app.models.brand import Brand
from app.models.user import User
from app.services.claude_service import generate_calendar_posts
from app.services.generation_tracker import update_generation_status, get_generation_status_cache, clear_generation_status
from app.services.persona_analyzer import analyze_buyer_personas, get_default_personas
from app.services.url_analyzer import get_brand_context_from_urls
from app.api.routes.auth import get_current_user

router = APIRouter()


class RegeneratePostRequest(BaseModel):
    user_prompt: str


class RegeneratePersonasRequest(BaseModel):
    feedback: str  # es: "target più giovane", "aggiungi professionisti tech"


class ConfirmPersonasRequest(BaseModel):
    personas: Optional[dict] = None  # Se l'utente ha modificato manualmente


# ============================================================
# STEP 1: GENERA BUYER PERSONAS
# ============================================================

@router.post("/personas/{project_id}")
async def generate_personas(
    project_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Genera buyer personas per il progetto.
    L'utente dovrà confermarle prima di generare il calendario.
    """
    project = db.query(Project).filter(Project.id == project_id).first()
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    
    brand = db.query(Brand).filter(Brand.id == project.brand_id).first()
    if not brand:
        raise HTTPException(status_code=404, detail="Brand not found")
    
    logger.info(f"[PERSONAS] Generating for project {project_id} - Brand: {brand.name}")
    
    # Analizza URL di riferimento
    url_context = ""
    reference_urls = project.reference_urls or []
    if not reference_urls and brand.website_url:
        reference_urls = [brand.website_url]
    
    if reference_urls:
        logger.info(f"[PERSONAS] Analyzing {len(reference_urls)} URLs...")
        try:
            url_context = await get_brand_context_from_urls(
                urls=reference_urls,
                brand_name=brand.name
            )
            logger.info(f"[PERSONAS] URL context: {len(url_context)} chars")
        except Exception as e:
            logger.info(f"[PERSONAS] URL analysis error: {e}")
    
    # Genera personas
    personas_data = await analyze_buyer_personas(
        brand_name=brand.name,
        sector=brand.sector,
        description=brand.description,
        target_audience=brand.target_audience or project.target_audience,
        products_services=brand.unique_selling_points,
        brand_values=brand.brand_values,
        tone_of_voice=brand.tone_of_voice,
        url_context=url_context,
        platforms=project.platforms,
        objectives=project.objectives
    )
    
    # Salva nel progetto (non ancora confermate)
    project.buyer_personas = personas_data
    
    # Aggiorna automaticamente posts_per_week con i suggerimenti AI
    if personas_data.get("recommended_posts_per_week"):
        # Filtra solo le piattaforme selezionate
        recommended = personas_data["recommended_posts_per_week"]
        project.posts_per_week = {p: recommended.get(p, 3) for p in (project.platforms or [])}
    db.commit()
    
    logger.info(f"[PERSONAS] Generated {len(personas_data.get('personas', []))} personas")
    
    return {
        "status": "generated",
        "personas": personas_data,
        "message": "Buyer personas generate. Rivedi e conferma per procedere."
    }


@router.post("/personas/{project_id}/regenerate")
async def regenerate_personas(
    project_id: int,
    request: RegeneratePersonasRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Rigenera buyer personas con feedback dell'utente.
    """
    project = db.query(Project).filter(Project.id == project_id).first()
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    
    brand = db.query(Brand).filter(Brand.id == project.brand_id).first()
    if not brand:
        raise HTTPException(status_code=404, detail="Brand not found")
    
    logger.info(f"[PERSONAS] Regenerating with feedback: {request.feedback[:100]}...")
    
    # Analizza URL
    url_context = ""
    reference_urls = project.reference_urls or []
    if not reference_urls and brand.website_url:
        reference_urls = [brand.website_url]
    
    if reference_urls:
        try:
            url_context = await get_brand_context_from_urls(
                urls=reference_urls,
                brand_name=brand.name
            )
        except:
            pass
    
    # Rigenera con feedback incluso nel target_audience
    enhanced_target = f"{brand.target_audience or ''}\n\nFEEDBACK UTENTE: {request.feedback}"
    
    personas_data = await analyze_buyer_personas(
        brand_name=brand.name,
        sector=brand.sector,
        description=brand.description,
        target_audience=enhanced_target,
        products_services=brand.unique_selling_points,
        brand_values=brand.brand_values,
        tone_of_voice=brand.tone_of_voice,
        url_context=url_context,
        platforms=project.platforms,
        objectives=project.objectives
    )
    
    # Aggiorna
    project.buyer_personas = personas_data
    
    # Aggiorna automaticamente posts_per_week con i suggerimenti AI
    if personas_data.get("recommended_posts_per_week"):
        # Filtra solo le piattaforme selezionate
        recommended = personas_data["recommended_posts_per_week"]
        project.posts_per_week = {p: recommended.get(p, 3) for p in (project.platforms or [])}
    db.commit()
    
    return {
        "status": "regenerated",
        "personas": personas_data,
        "message": "Personas rigenerate con le tue indicazioni."
    }


@router.put("/personas/{project_id}/confirm")
async def confirm_personas(
    project_id: int,
    request: Optional[ConfirmPersonasRequest] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Conferma le buyer personas (eventualmente modificate manualmente).
    """
    project = db.query(Project).filter(Project.id == project_id).first()
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    
    # Se l'utente ha passato personas modificate, usale
    if request and request.personas:
        project.buyer_personas = request.personas
    
    # Marca come confermate
    if project.buyer_personas:
        project.buyer_personas["confirmed"] = True
        project.buyer_personas["confirmed_at"] = __import__('datetime').datetime.now().isoformat()
    
    db.commit()
    
    return {
        "status": "confirmed",
        "message": "Personas confermate. Ora puoi generare il calendario."
    }


@router.get("/personas/{project_id}")
async def get_personas(
    project_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Recupera le buyer personas del progetto"""
    project = db.query(Project).filter(Project.id == project_id).first()
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    
    return {
        "personas": project.buyer_personas,
        "confirmed": project.buyer_personas.get("confirmed", False) if project.buyer_personas else False
    }


# ============================================================
# STEP 2: GENERA CALENDARIO (dopo conferma personas)
# ============================================================

def run_generation(project_id: int):
    """Esegue la generazione in background"""
    db = SessionLocal()
    try:
        project = db.query(Project).filter(Project.id == project_id).first()
        if not project:
            logger.info(f"[GEN] Project {project_id} not found")
            return
        
        brand = db.query(Brand).filter(Brand.id == project.brand_id).first()
        if not brand:
            logger.info(f"[GEN] Brand not found for project {project_id}")
            return
        
        logger.info(f"[GEN] Starting generation for project {project_id} - Brand: {brand.name}")
        
        # Recupera buyer personas (devono essere già generate/confermate)
        buyer_personas = project.buyer_personas
        if not buyer_personas:
            logger.info(f"[GEN] No personas found, using defaults")
            buyer_personas = get_default_personas(project.platforms)
        
        # Analizza URL se serve contesto aggiuntivo
        url_context = ""
        reference_urls = project.reference_urls or []
        if reference_urls:
            logger.info(f"[GEN] Analyzing {len(reference_urls)} reference URLs...")
            try:
                loop = asyncio.new_event_loop()
                asyncio.set_event_loop(loop)
                url_context = loop.run_until_complete(
                    get_brand_context_from_urls(
                        urls=reference_urls,
                        brand_name=brand.name
                    )
                )
                loop.close()
                logger.info(f"[GEN] URL context: {len(url_context)} chars")
            except Exception as e:
                logger.info(f"[GEN] URL analysis error: {e}")
        
        # Prepara posts_per_week
        posts_per_week = {}
        if project.platforms:
            for p in project.platforms:
                posts_per_week[p] = project.posts_per_week.get(p, 2) if project.posts_per_week else 2
        
        # Prepara brand_info e project_info
        brand_info = {
            "sector": brand.sector,
            "description": brand.description,
            "target_audience": brand.target_audience,
            "unique_selling_points": brand.unique_selling_points,
            "brand_values": brand.brand_values,
            "tone_of_voice": brand.tone_of_voice,
            "style_guide": brand.style_guide
        }
        
        project_info = {
            "brief": project.brief,
            "target_audience": project.target_audience,
            "custom_prompt": project.custom_prompt,
            "objectives": project.objectives or []
        }
        
        themes = project.content_pillars or project.themes or []
        
        # Genera con async
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        posts, updated_personas = loop.run_until_complete(
            generate_calendar_posts(
                brand_name=brand.name,
                brand_info=brand_info,
                project_info=project_info,
                start_date=project.start_date,
                end_date=project.end_date,
                platforms=project.platforms or [],
                posts_per_week=posts_per_week,
                themes=themes,
                url_context=url_context,
                style_guide=brand.style_guide,
                buyer_personas=buyer_personas,
                brand_id=brand.id,
                project_id=project_id,
                db=db
            )
        )
        loop.close()
        
        logger.info(f"[GEN] Claude returned {len(posts)} posts")
        
        # Reset any failed transaction
        db.rollback()
        
        # Delete only posts in the project date range (keep historical posts)
        # Cancella solo nel range del progetto
        deleted = db.query(Post).filter(
            Post.project_id == project_id,
            Post.scheduled_date >= project.start_date,
            Post.scheduled_date <= project.end_date
        ).delete()
        logger.info(f"[GEN] Deleted {deleted} future posts (kept past posts)")
        
        # Save new posts
        for post_data in posts:
            post = Post(
                project_id=project_id,
                platform=post_data.get("platform", ""),
                scheduled_date=post_data.get("scheduled_date"),
                scheduled_time=post_data.get("scheduled_time", "09:00"),
                content=post_data.get("content", ""),
                hashtags=post_data.get("hashtags", []),
                pillar=post_data.get("pillar", ""),
                post_type=post_data.get("post_type", ""),
                content_type=post_data.get("content_type", "post"),
                visual_suggestion=post_data.get("visual_suggestion", ""),
                call_to_action=post_data.get("call_to_action", ""),
                cta=post_data.get("cta", "")
            )
            db.add(post)
        
        # Aggiorna personas se rigenerate
        if updated_personas:
            project.buyer_personas = updated_personas
        
        project.status = ProjectStatus.review
        db.commit()
        logger.info(f"[GEN] ✅ Saved {len(posts)} posts, status set to review")
        
    except Exception as e:
        logger.info(f"[GEN] ❌ Error: {e}")
        import traceback
        traceback.print_exc()
        try:
            project = db.query(Project).filter(Project.id == project_id).first()
            if project:
                project.status = ProjectStatus.draft
                db.commit()
        except:
            pass
    finally:
        db.close()


@router.post("/calendar/{project_id}")
def generate_calendar(
    project_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Genera il calendario editoriale.
    Richiede che le buyer personas siano state generate (opzionalmente confermate).
    """
    project = db.query(Project).filter(Project.id == project_id).first()
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    
    # Warning se personas non confermate (ma procedi comunque)
    personas_status = "not_generated"
    if project.buyer_personas:
        personas_status = "confirmed" if project.buyer_personas.get("confirmed") else "generated"
    
    project.status = ProjectStatus.generating
    db.commit()
    
    thread = threading.Thread(target=run_generation, args=(project_id,))
    thread.start()
    
    return {
        "status": "generating",
        "personas_status": personas_status,
        "message": "Generazione avviata"
    }


# ============================================================
# STATUS E ALTRI ENDPOINT
# ============================================================

@router.get("/status/{project_id}")
def get_generation_status(
    project_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    project = db.query(Project).filter(Project.id == project_id).first()
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    
    post_count = db.query(Post).filter(Post.project_id == project_id).count()
    
    # Calcola progress
    percent = 0
    current_batch = 0
    total_batches = 0
    
    if project.status == ProjectStatus.generating:
        # Usa il cache per stato reale
        cached = get_generation_status_cache(project_id)
        if cached:
            current_batch = cached["current_batch"]
            total_batches = cached["total_batches"]
            percent = cached["percent"]
        else:
            # Fallback: stima iniziale
            total_days = (project.end_date - project.start_date).days + 1
            total_batches = (total_days + 13) // 14
            percent = 0
            current_batch = 0
    elif project.status == ProjectStatus.review:
        percent = 100
        clear_generation_status(project_id)
    
    return {
        "status": project.status.value if project.status else "draft",
        "post_count": post_count,
        "percent": percent,
        "current_batch": current_batch,
        "total_batches": total_batches
    }


@router.post("/regenerate-post/{post_id}")
def regenerate_post(
    post_id: int,
    request: RegeneratePostRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Rigenera un singolo post con AI"""
    from app.services.claude_service_legacy import regenerate_single_post
    
    post = db.query(Post).filter(Post.id == post_id).first()
    if not post:
        raise HTTPException(status_code=404, detail="Post not found")
    
    project = db.query(Project).filter(Project.id == post.project_id).first()
    brand = db.query(Brand).filter(Brand.id == project.brand_id).first() if project else None
    
    result = regenerate_single_post(
        post_content=post.content,
        platform=post.platform,
        pillar=post.pillar or "",
        user_prompt=request.user_prompt,
        brand_context=f"{brand.name} - {brand.sector}" if brand else "",
        tone_of_voice=brand.tone_of_voice if brand else "",
        brand_style_guide=brand.style_guide if brand else ""
    )
    
    post.content = result.get("content", post.content)
    post.hashtags = result.get("hashtags", post.hashtags)
    post.visual_suggestion = result.get("visual_suggestion", post.visual_suggestion)
    post.cta = result.get("cta", post.cta)
    db.commit()
    db.refresh(post)
    
    return {
        "id": post.id,
        "content": post.content,
        "hashtags": post.hashtags,
        "visual_suggestion": post.visual_suggestion,
        "cta": post.cta
    }


@router.post("/image-prompt/{post_id}")
def generate_image_prompt_endpoint(
    post_id: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Genera prompt per immagine AI"""
    from app.services.claude_service_legacy import generate_image_prompt
    
    post = db.query(Post).filter(Post.id == post_id).first()
    if not post:
        raise HTTPException(status_code=404, detail="Post not found")
    
    project = db.query(Project).filter(Project.id == post.project_id).first()
    brand = db.query(Brand).filter(Brand.id == project.brand_id).first() if project else None
    
    image_prompt = generate_image_prompt(
        post_content=post.content,
        platform=post.platform,
        pillar=post.pillar or "",
        brand_name=brand.name if brand else "",
        brand_sector=brand.sector if brand else "",
        brand_colors=brand.colors if brand else ""
    )
    
    post.image_prompt = image_prompt
    db.commit()
    
    return {"image_prompt": image_prompt}


# === GESTIONE SINGOLE PERSONAS ===

class SinglePersonaRequest(BaseModel):
    persona_description: Optional[str] = None  # Descrizione per generare nuova persona

@router.post("/personas/{project_id}/add")
async def add_persona(
    project_id: int,
    request: SinglePersonaRequest = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Aggiunge una nuova buyer persona al progetto"""
    project = db.query(Project).filter(Project.id == project_id).first()
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    
    brand = db.query(Brand).filter(Brand.id == project.brand_id).first()
    if not brand:
        raise HTTPException(status_code=404, detail="Brand not found")
    
    # Genera una nuova persona
    from app.services.persona_analyzer import analyze_buyer_personas
    
    extra_context = ""
    if request and request.persona_description:
        extra_context = f"\n\nCrea UNA SOLA nuova persona con queste caratteristiche: {request.persona_description}"
    else:
        extra_context = "\n\nCrea UNA SOLA nuova persona diversa dalle esistenti."
    
    # Genera una singola persona
    new_persona_data = await analyze_buyer_personas(
        brand_name=brand.name,
        description=brand.description or "",
        sector=brand.sector or "",
        target_audience=project.target_audience or "",
        platforms=project.platforms or [],
        
    )
    
    # Aggiungi la nuova persona all'elenco esistente
    if not project.buyer_personas:
        project.buyer_personas = {"personas": []}
    
    if new_persona_data.get("personas"):
        new_persona = new_persona_data["personas"][0]
        project.buyer_personas["personas"].append(new_persona)
        project.buyer_personas["confirmed"] = False
        
        from sqlalchemy.orm.attributes import flag_modified
        flag_modified(project, "buyer_personas")
        db.commit()
        
        return {
            "success": True,
            "persona": new_persona,
            "total_personas": len(project.buyer_personas["personas"])
        }
    
    raise HTTPException(status_code=500, detail="Errore nella generazione della persona")


@router.delete("/personas/{project_id}/{persona_index}")
async def delete_persona(
    project_id: int,
    persona_index: int,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Elimina una singola buyer persona dal progetto"""
    project = db.query(Project).filter(Project.id == project_id).first()
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    
    if not project.buyer_personas or "personas" not in project.buyer_personas:
        raise HTTPException(status_code=404, detail="Nessuna persona trovata")
    
    personas = project.buyer_personas["personas"]
    if persona_index < 0 or persona_index >= len(personas):
        raise HTTPException(status_code=404, detail="Indice persona non valido")
    
    deleted_persona = personas.pop(persona_index)
    project.buyer_personas["confirmed"] = False
    
    from sqlalchemy.orm.attributes import flag_modified
    flag_modified(project, "buyer_personas")
    db.commit()
    
    return {
        "success": True,
        "deleted": deleted_persona["name"],
        "remaining_personas": len(personas)
    }


@router.post("/personas/{project_id}/{persona_index}/regenerate")
async def regenerate_single_persona(
    project_id: int,
    persona_index: int,
    request: SinglePersonaRequest = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Rigenera una singola buyer persona"""
    project = db.query(Project).filter(Project.id == project_id).first()
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    
    brand = db.query(Brand).filter(Brand.id == project.brand_id).first()
    if not brand:
        raise HTTPException(status_code=404, detail="Brand not found")
    
    if not project.buyer_personas or "personas" not in project.buyer_personas:
        raise HTTPException(status_code=404, detail="Nessuna persona trovata")
    
    personas = project.buyer_personas["personas"]
    if persona_index < 0 or persona_index >= len(personas):
        raise HTTPException(status_code=404, detail="Indice persona non valido")
    
    old_persona = personas[persona_index]
    
    from app.services.persona_analyzer import analyze_buyer_personas
    
    extra_context = f"""
Rigenera la persona "{old_persona.get('name', 'Persona ' + str(persona_index + 1))}" con caratteristiche DIVERSE.
Le altre personas esistenti sono: {[p.get('name') for p in personas if p != old_persona]}
Crea UNA SOLA persona completamente nuova e diversa dalla precedente.
"""
    
    if request and request.persona_description:
        extra_context += f"\nIndicazioni aggiuntive: {request.persona_description}"
    
    new_persona_data = await analyze_buyer_personas(
        brand_name=brand.name,
        description=brand.description or "",
        sector=brand.sector or "",
        target_audience=project.target_audience or "",
        platforms=project.platforms or [],
        
    )
    
    if new_persona_data.get("personas"):
        new_persona = new_persona_data["personas"][0]
        personas[persona_index] = new_persona
        project.buyer_personas["confirmed"] = False
        
        from sqlalchemy.orm.attributes import flag_modified
        flag_modified(project, "buyer_personas")
        db.commit()
        
        return {
            "success": True,
            "old_persona": old_persona["name"],
            "new_persona": new_persona
        }
    
    raise HTTPException(status_code=500, detail="Errore nella rigenerazione della persona")


@router.post("/check-overlap/{project_id}")
def check_overlap(
    project_id: int,
    new_dates: dict,  # {"start_date": "2026-03-01", "end_date": "2026-03-31"}
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Verifica se ci sono post esistenti nel range di date specificato.
    Ritorna info sui post che verranno sovrascritti.
    """
    from datetime import datetime
    
    project = db.query(Project).filter(Project.id == project_id).first()
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    
    start_date = datetime.strptime(new_dates["start_date"], "%Y-%m-%d").date()
    end_date = datetime.strptime(new_dates["end_date"], "%Y-%m-%d").date()
    
    # Conta post esistenti nel range
    overlapping_posts = db.query(Post).filter(
        Post.project_id == project_id,
        Post.scheduled_date >= start_date,
        Post.scheduled_date <= end_date
    ).all()
    
    # Raggruppa per piattaforma
    by_platform = {}
    for post in overlapping_posts:
        plat = post.platform or "altro"
        if plat not in by_platform:
            by_platform[plat] = 0
        by_platform[plat] += 1
    
    # Post fuori dal range (che verranno mantenuti)
    kept_posts = db.query(Post).filter(
        Post.project_id == project_id,
        (Post.scheduled_date < start_date) | (Post.scheduled_date > end_date)
    ).count()
    
    return {
        "has_overlap": len(overlapping_posts) > 0,
        "overlapping_count": len(overlapping_posts),
        "overlapping_by_platform": by_platform,
        "kept_count": kept_posts,
        "date_range": {
            "start": start_date.isoformat(),
            "end": end_date.isoformat()
        },
        "current_project_dates": {
            "start": project.start_date.isoformat() if project.start_date else None,
            "end": project.end_date.isoformat() if project.end_date else None
        }
    }


@router.post("/check-overlap/{project_id}")
def check_overlap(
    project_id: int,
    new_dates: dict,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """
    Verifica se ci sono post esistenti nel range di date specificato.
    Ritorna info sui post che verranno sovrascritti.
    """
    from datetime import datetime
    
    project = db.query(Project).filter(Project.id == project_id).first()
    if not project:
        raise HTTPException(status_code=404, detail="Project not found")
    
    start_date = datetime.strptime(new_dates["start_date"], "%Y-%m-%d").date()
    end_date = datetime.strptime(new_dates["end_date"], "%Y-%m-%d").date()
    
    # Conta post esistenti nel range
    overlapping_posts = db.query(Post).filter(
        Post.project_id == project_id,
        Post.scheduled_date >= start_date,
        Post.scheduled_date <= end_date
    ).all()
    
    # Raggruppa per piattaforma
    by_platform = {}
    for post in overlapping_posts:
        plat = post.platform or "altro"
        if plat not in by_platform:
            by_platform[plat] = 0
        by_platform[plat] += 1
    
    # Post fuori dal range (che verranno mantenuti)
    kept_posts = db.query(Post).filter(
        Post.project_id == project_id,
        ((Post.scheduled_date < start_date) | (Post.scheduled_date > end_date))
    ).count()
    
    return {
        "has_overlap": len(overlapping_posts) > 0,
        "overlapping_count": len(overlapping_posts),
        "overlapping_by_platform": by_platform,
        "kept_count": kept_posts,
        "date_range": {
            "start": start_date.isoformat(),
            "end": end_date.isoformat()
        },
        "current_project_dates": {
            "start": project.start_date.isoformat() if project.start_date else None,
            "end": project.end_date.isoformat() if project.end_date else None
        }
    }
