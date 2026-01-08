import anthropic
import json
import os
import asyncio
import logging
from datetime import datetime, timedelta

logger = logging.getLogger(__name__)

# Import persona analyzer
from app.services.persona_analyzer import analyze_buyer_personas, get_scheduling_for_platform, get_default_personas
from app.services.rag_service import rag_service

DEFAULT_STYLE_GUIDE = """
LINEE GUIDA CONTENUTI:
- Tono professionale ma accessibile
- Focus su valore pratico per il lettore
- Call-to-action chiare ma non aggressive
- Hashtag pertinenti e non eccessivi (3-5 per post social)
- Contenuti ottimizzati per ogni piattaforma
"""


async def generate_calendar_posts(
    brand_name: str,
    brand_info: dict,
    project_info: dict,
    start_date: datetime,
    end_date: datetime,
    platforms: list,
    posts_per_week: dict,
    themes: list = None,
    url_context: str = None,
    style_guide: str = None,
    buyer_personas: dict = None,
    brand_id: int = None,
    db = None
) -> tuple[list, dict]:
    """
    Genera post per il calendario editoriale.
    Returns: (posts_list, personas_data)
    """
    client = anthropic.Anthropic(api_key=os.getenv("ANTHROPIC_API_KEY"))
    
    # STEP 0: Recupera contesto dalla Knowledge Base (RAG)
    rag_context = ""
    if brand_id and db:
        try:
            # Cerca contesto rilevante basato su brief e temi
            search_query = f"{brand_name} {project_info.get('brief', '')} {' '.join(themes or [])}"
            rag_context = rag_service.get_context_for_generation(db, brand_id, search_query, max_tokens=3000)
            if rag_context:
                logger.info(f"[RAG] Found relevant context from documents ({len(rag_context)} chars)")
        except Exception as e:
            logger.warning(f"[RAG] Error getting context: {e}")
    
    # STEP 1: Analizza/genera buyer personas se non fornite
    if not buyer_personas:
        logger.info("[CLAUDE] Generating buyer personas...")
        buyer_personas = await analyze_buyer_personas(
            brand_name=brand_name,
            sector=brand_info.get("sector"),
            description=brand_info.get("description"),
            target_audience=brand_info.get("target_audience") or project_info.get("target_audience"),
            products_services=brand_info.get("unique_selling_points"),
            brand_values=brand_info.get("brand_values"),
            tone_of_voice=brand_info.get("tone_of_voice"),
            url_context=url_context,
            platforms=platforms
        )
        logger.info(f"[CLAUDE] Personas generated: {len(buyer_personas.get('personas', []))} personas")
    
    # STEP 2: Genera contenuti in batch
    all_posts = []
    total_days = (end_date - start_date).days + 1
    batch_size = 14
    batches = (total_days + batch_size - 1) // batch_size
    
    for batch_num in range(batches):
        batch_start = start_date + timedelta(days=batch_num * batch_size)
        batch_end = min(batch_start + timedelta(days=batch_size - 1), end_date)
        
        logger.info(f"[CLAUDE] Batch {batch_num + 1}/{batches}: {batch_start} to {batch_end}")
        
        if batch_num > 0:
            logger.info("[CLAUDE] Waiting 8s for rate limit...")
            await asyncio.sleep(8)
        
        posts = await generate_batch(
            client=client,
            brand_name=brand_name,
            brand_info=brand_info,
            project_info=project_info,
            start_date=batch_start,
            end_date=batch_end,
            platforms=platforms,
            posts_per_week=posts_per_week,
            themes=themes,
            url_context=url_context,
            rag_context=rag_context,
            style_guide=style_guide or DEFAULT_STYLE_GUIDE,
            buyer_personas=buyer_personas,
            batch_num=batch_num + 1,
            total_batches=batches
        )
        
        logger.info(f"[CLAUDE] Batch {batch_num + 1} returned {len(posts)} posts")
        all_posts.extend(posts)
    
    # STEP 3: Redistribuisci con scheduling da personas
    all_posts = redistribute_posts_with_personas(all_posts, posts_per_week, start_date, end_date, buyer_personas)
    
    logger.info(f"[CLAUDE] Total posts generated: {len(all_posts)}")
    
    return all_posts, buyer_personas


async def generate_batch(
    client,
    brand_name: str,
    brand_info: dict,
    project_info: dict,
    start_date: datetime,
    end_date: datetime,
    platforms: list,
    posts_per_week: dict,
    themes: list,
    url_context: str,
    rag_context: str,
    style_guide: str,
    buyer_personas: dict,
    batch_num: int,
    total_batches: int
) -> list:
    """Genera un batch di post"""
    
    # Estrai scheduling strategy dalle personas
    scheduling_info = format_scheduling_from_personas(buyer_personas, platforms)
    
    prompt = f"""Genera contenuti per il calendario editoriale.

## BRAND
Nome: {brand_name}
Settore: {brand_info.get('sector', 'N/A')}
Descrizione: {brand_info.get('description', 'N/A')}
Tono di voce: {brand_info.get('tone_of_voice', 'professionale')}
Valori: {brand_info.get('brand_values', [])}

## CONTESTO DAL SITO
{url_context or 'Non disponibile'}

## KNOWLEDGE BASE AZIENDALE
{rag_context or 'Non disponibile'}

## BUYER PERSONAS
{format_personas_for_prompt(buyer_personas)}

## SCHEDULING OTTIMALE (basato sulle personas)
{scheduling_info}

## PROGETTO
Periodo: {start_date.strftime('%Y-%m-%d')} - {end_date.strftime('%Y-%m-%d')}
Piattaforme: {', '.join(platforms)}
Post per settimana: {json.dumps(posts_per_week)}
Temi: {', '.join(themes) if themes else 'Generici per il settore'}
Brief: {project_info.get('brief', 'N/A')}

## LINEE GUIDA
{style_guide}

## ISTRUZIONI
1. Genera ESATTAMENTE i post richiesti per questo periodo
2. USA GLI ORARI E I GIORNI indicati nello scheduling (derivano dall'analisi delle buyer personas)
3. Adatta il tono e contenuto alle personas identificate
4. Ogni post deve avere: platform, date (YYYY-MM-DD), time (HH:MM), content, hashtags, content_type, pillar

## FORMATO OUTPUT (JSON array)
[
  {{
    "platform": "linkedin",
    "scheduled_date": "2025-01-07",
    "scheduled_time": "08:30",
    "content": "Testo del post...",
    "hashtags": ["hashtag1", "hashtag2"],
    "content_type": "educational",
    "pillar": "thought leadership"
  }}
]

Rispondi SOLO con il JSON array, senza markdown.
"""

    logger.info(f"[CLAUDE] Calling API - Brand: {brand_name}, Period: {start_date} to {end_date}")
    
    try:
        response = client.messages.create(
            model="claude-sonnet-4-20250514",
            max_tokens=8000,
            messages=[{"role": "user", "content": prompt}]
        )
        
        content = response.content[0].text.strip()
        logger.info(f"[CLAUDE] Response length: {len(content)} chars")
        
        # Parse JSON
        if content.startswith("```"):
            content = content.split("```")[1]
            if content.startswith("json"):
                content = content[4:]
        content = content.strip()
        
        posts = json.loads(content)
        logger.info(f"[CLAUDE] Parsed {len(posts)} posts")
        
        return posts
        
    except json.JSONDecodeError as e:
        logger.error(f"[CLAUDE] JSON parse error: {e}")
        logger.error(f"[CLAUDE] Raw content: {content[:500]}")
        return []
    except Exception as e:
        logger.error(f"[CLAUDE] API error: {e}")
        return []


def format_personas_for_prompt(personas_data: dict) -> str:
    """Formatta le personas per il prompt"""
    if not personas_data or "personas" not in personas_data:
        return "Nessuna persona specifica - usa target generico B2B"
    
    lines = []
    for p in personas_data.get("personas", []):
        name = p.get("name", "Persona")
        demo = p.get("demographics", {})
        weight = p.get("weight", 0)
        pain_points = p.get("pain_points", [])
        interests = p.get("interests", [])
        
        lines.append(f"""
### {name} (peso: {weight*100:.0f}%)
- Profilo: {demo.get('age_range', 'N/A')}, {demo.get('role', 'N/A')}, {demo.get('location', 'N/A')}
- Pain points: {', '.join(pain_points[:3]) if pain_points else 'N/A'}
- Interessi: {', '.join(interests[:3]) if interests else 'N/A'}
""")
    
    return "\n".join(lines)


def format_scheduling_from_personas(personas_data: dict, platforms: list) -> str:
    """Formatta lo scheduling strategy per il prompt"""
    strategy = personas_data.get("scheduling_strategy", {})
    
    lines = []
    for platform in platforms:
        plat_strategy = strategy.get(platform, {})
        slots = plat_strategy.get("optimal_slots", [])
        avoid = plat_strategy.get("avoid", [])
        
        day_names = ["Lunedì", "Martedì", "Mercoledì", "Giovedì", "Venerdì", "Sabato", "Domenica"]
        
        slot_strs = []
        for s in sorted(slots, key=lambda x: x.get("priority", 99))[:3]:
            day = s.get("day", 0)
            time = s.get("time", "12:00")
            slot_strs.append(f"{day_names[day]} {time}")
        
        lines.append(f"- {platform.upper()}: {', '.join(slot_strs) if slot_strs else 'flessibile'}")
        if avoid:
            lines.append(f"  (evitare: {', '.join(avoid[:2])})")
    
    return "\n".join(lines)


def redistribute_posts_with_personas(
    posts: list,
    posts_per_week: dict,
    start_date: datetime,
    end_date: datetime,
    personas_data: dict
) -> list:
    """
    Redistribuisce i post usando lo scheduling delle buyer personas.
    """
    if not posts:
        return []
    
    logger.info(f"[CLAUDE] Redistributing {len(posts)} posts with persona-based scheduling")
    
    strategy = personas_data.get("scheduling_strategy", {})
    
    # Raggruppa post per piattaforma
    by_platform = {}
    for post in posts:
        plat = post.get("platform", "").lower()
        if plat not in by_platform:
            by_platform[plat] = []
        by_platform[plat].append(post)
    
    redistributed = []
    total_days = (end_date - start_date).days + 1
    total_weeks = (total_days + 6) // 7
    
    for platform, platform_posts in by_platform.items():
        # Ottieni slots dalla strategy
        plat_strategy = strategy.get(platform, {})
        optimal_slots = plat_strategy.get("optimal_slots", [])
        
        # Fallback se non ci sono slots
        if not optimal_slots:
            optimal_slots = [
                {"day": 1, "time": "10:00", "priority": 1},
                {"day": 3, "time": "10:00", "priority": 2}
            ]
        
        # Ordina per priorità
        sorted_slots = sorted(optimal_slots, key=lambda x: x.get("priority", 99))
        
        # Calcola quanti post per settimana
        ppw = posts_per_week.get(platform, 2)
        
        post_idx = 0
        for week in range(total_weeks):
            week_start = start_date + timedelta(weeks=week)
            
            for slot_num, slot in enumerate(sorted_slots):
                if slot_num >= ppw or post_idx >= len(platform_posts):
                    break
                
                day_of_week = slot.get("day", 0)
                time = slot.get("time", "10:00")
                
                # Calcola la data effettiva
                days_until_target = (day_of_week - week_start.weekday()) % 7
                post_date = week_start + timedelta(days=days_until_target)
                
                # Verifica che sia nel range
                if post_date < start_date:
                    post_date += timedelta(days=7)
                if post_date > end_date:
                    continue
                
                post = platform_posts[post_idx].copy()
                post["scheduled_date"] = post_date.strftime("%Y-%m-%d")
                post["scheduled_time"] = time
                redistributed.append(post)
                post_idx += 1
    
    # Ordina per data e ora
    redistributed.sort(key=lambda x: (x.get("scheduled_date", ""), x.get("scheduled_time", "")))
    
    logger.info(f"[CLAUDE] Redistributed to {len(redistributed)} posts with persona scheduling")
    
    # Log distribuzione
    platform_counts = {}
    for p in redistributed:
        plat = p.get("platform", "unknown")
        platform_counts[plat] = platform_counts.get(plat, 0) + 1
    logger.info(f"[CLAUDE] Distribution: {platform_counts}")
    
    return redistributed


# === LEGACY FUNCTIONS (for posts.py compatibility) ===

def generate_editorial_plan(
    brand_name: str,
    brand_sector: str,
    tone_of_voice: str,
    brand_values: str,
    start_date: str,
    end_date: str,
    platforms: list,
    posts_per_week: dict,
    brief: str,
    themes: list,
    custom_prompt: str = "",
    brand_style_guide: str = "",
    urls_content: str = ""
) -> list:
    """Legacy function for generating editorial plan (synchronous wrapper)"""
    import anthropic
    
    client = anthropic.Anthropic(api_key=os.getenv("ANTHROPIC_API_KEY"))
    
    prompt = f"""Genera un piano editoriale.

## BRAND
Nome: {brand_name}
Settore: {brand_sector}
Tono di voce: {tone_of_voice}
Valori: {brand_values}

## CONTESTO
{urls_content if urls_content else 'Non disponibile'}

## PROGETTO
Periodo: {start_date} - {end_date}
Piattaforme: {', '.join(platforms)}
Post per settimana: {json.dumps(posts_per_week)}
Temi: {', '.join(themes) if themes else 'Generici'}
Brief: {brief}

## LINEE GUIDA
{brand_style_guide or DEFAULT_STYLE_GUIDE}

{custom_prompt}

## FORMATO OUTPUT (JSON array)
[
  {{
    "platform": "linkedin",
    "scheduled_date": "2025-01-07",
    "scheduled_time": "09:00",
    "content": "Testo del post...",
    "hashtags": ["hashtag1", "hashtag2"],
    "post_type": "educational",
    "pillar": "thought leadership",
    "visual_suggestion": "Descrizione immagine",
    "cta": "Call to action"
  }}
]

Rispondi SOLO con il JSON array.
"""
    
    try:
        response = client.messages.create(
            model="claude-sonnet-4-20250514",
            max_tokens=8000,
            messages=[{"role": "user", "content": prompt}]
        )
        
        content = response.content[0].text.strip()
        
        if content.startswith("```"):
            content = content.split("```")[1]
            if content.startswith("json"):
                content = content[4:]
        content = content.strip()
        
        return json.loads(content)
        
    except Exception as e:
        logger.error(f"[CLAUDE] generate_editorial_plan error: {e}")
        return []


def regenerate_single_post(
    post_content: str,
    platform: str,
    pillar: str,
    user_prompt: str,
    brand_context: str,
    tone_of_voice: str,
    brand_style_guide: str = ""
) -> dict:
    """Regenerate a single post with AI"""
    import anthropic
    
    client = anthropic.Anthropic(api_key=os.getenv("ANTHROPIC_API_KEY"))
    
    prompt = f"""Rigenera questo post social.

## POST ORIGINALE
Piattaforma: {platform}
Pillar: {pillar}
Contenuto: {post_content}

## CONTESTO BRAND
{brand_context}
Tono di voce: {tone_of_voice}

## ISTRUZIONI UTENTE
{user_prompt}

## LINEE GUIDA
{brand_style_guide or DEFAULT_STYLE_GUIDE}

## OUTPUT (JSON)
{{
  "content": "Nuovo testo del post",
  "hashtags": ["hashtag1", "hashtag2"],
  "visual_suggestion": "Suggerimento per visual",
  "cta": "Call to action"
}}

Rispondi SOLO con il JSON.
"""
    
    try:
        response = client.messages.create(
            model="claude-sonnet-4-20250514",
            max_tokens=2000,
            messages=[{"role": "user", "content": prompt}]
        )
        
        content = response.content[0].text.strip()
        
        if content.startswith("```"):
            content = content.split("```")[1]
            if content.startswith("json"):
                content = content[4:]
        content = content.strip()
        
        return json.loads(content)
        
    except Exception as e:
        logger.error(f"[CLAUDE] regenerate_single_post error: {e}")
        return {"content": post_content}


def generate_image_prompt(
    post_content: str,
    platform: str,
    pillar: str,
    brand_name: str,
    brand_sector: str,
    brand_colors: str = "",
    visual_suggestion: str = ""
) -> str:
    """Generate a detailed DALL-E prompt for a post image"""
    import anthropic
    
    client = anthropic.Anthropic(api_key=os.getenv("ANTHROPIC_API_KEY"))
    
    prompt = f"""Crea un prompt dettagliato per DALL-E per generare un'immagine per questo post social.

## POST
Piattaforma: {platform}
Contenuto: {post_content}
Pillar: {pillar}

## BRAND
Nome: {brand_name}
Settore: {brand_sector}
Colori: {brand_colors or 'Non specificati'}
Stile richiesto: {visual_suggestion or 'Non specificato'}

## ISTRUZIONI
- Crea un prompt in inglese per DALL-E
- Stile professionale e moderno
- Adatto per {platform}
- IMPORTANTE: Nessun testo, nessuna scritta, nessuna parola, nessun numero nell'immagine
- NO loghi o marchi
- Formato: descrizione dettagliata in 1-2 frasi

Rispondi SOLO con il prompt in inglese, senza altro testo.
"""
    
    try:
        response = client.messages.create(
            model="claude-sonnet-4-20250514",
            max_tokens=500,
            messages=[{"role": "user", "content": prompt}]
        )
        
        return response.content[0].text.strip()
        
    except Exception as e:
        logger.error(f"[CLAUDE] generate_image_prompt error: {e}")
        return f"Professional {brand_sector} business image, modern and clean style"
