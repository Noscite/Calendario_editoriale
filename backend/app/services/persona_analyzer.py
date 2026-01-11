"""
Buyer Persona Analyzer - AI-driven persona generation
Analizza brand/azienda e genera automaticamente buyer personas
con comportamenti digitali e orari ottimali per piattaforma.
"""
import anthropic
import json
import os
import logging
from app.services.perplexity_scheduling_research import research_optimal_schedule
from datetime import datetime

logger = logging.getLogger(__name__)

PERSONA_ANALYSIS_PROMPT = """Sei un esperto di marketing digitale e analisi comportamentale.

Analizza le seguenti informazioni sull'azienda/brand e genera le BUYER PERSONAS più probabili.

## INFORMAZIONI BRAND
- Nome: {brand_name}
- Settore: {sector}
- Descrizione: {description}
- Target dichiarato: {target_audience}
- Prodotti/Servizi: {products_services}
- Valori brand: {brand_values}
- Tono di voce: {tone_of_voice}

## CONTESTO DAL SITO WEB
{url_context}

## PIATTAFORME ATTIVE
{platforms}

## IL TUO COMPITO

Genera 2-3 buyer personas REALISTICHE per questo brand e CALCOLA LA FREQUENZA OTTIMALE di posting.

### ANALISI FREQUENZA POSTING
Basandoti su:
- Tipo di brand (B2B vs B2C)
- Risorse presumibili dell'azienda
- Comportamento delle buyer personas
- Best practices del settore

Determina quanti post a settimana per ogni piattaforma. Linee guida:
- LinkedIn B2B: 2-4/settimana (qualità > quantità)
- LinkedIn B2C: 3-5/settimana
- Instagram B2B: 3-4/settimana
- Instagram B2C: 5-7/settimana (incluse stories)
- Facebook: 1-3/settimana (reach organico basso)
- Newsletter: 1-2/settimana max
- Blog: 1-2/settimana

### PER OGNI PERSONA, DEFINISCI:

1. **Profilo demografico**: età, genere, ruolo, area geografica, reddito
2. **Comportamento digitale**: quando e come usa ogni piattaforma
3. **Orari ottimali di accesso** per OGNI piattaforma (basati su statistiche reali del mercato italiano 2024-2025)
4. **Pain points**: problemi che il brand può risolvere
5. **Interessi**: topic che catturano l'attenzione
6. **Weight**: importanza relativa (0.0-1.0, totale deve fare 1.0)

IMPORTANTE sugli ORARI:
- LinkedIn B2B Italia: 7:30-8:30 mattina, 12:30-13:30 pausa pranzo (martedì-giovedì top)
- Instagram consumer: 12:00-13:00, 19:00-21:00 (weekend inclusi)
- Facebook: 13:00-16:00, weekend mattina
- Newsletter B2B: 7:00-8:00 martedì/giovedì
- Newsletter consumer: 10:00-11:00 o 20:00-21:00

Adatta questi orari alla SPECIFICA buyer persona (es. una mamma casalinga ha orari diversi da un manager).

## FORMATO OUTPUT (JSON VALIDO)
```json
{{
  "personas": [
    {{
      "name": "Nome descrittivo - Ruolo",
      "demographics": {{
        "age_range": "35-50",
        "gender": "prevalentemente femminile",
        "role": "Titolare PMI / Manager",
        "location": "Nord Italia, aree urbane",
        "income": "medio-alto"
      }},
      "digital_behavior": {{
        "linkedin": {{
          "usage": "alta - uso professionale quotidiano",
          "best_days": [1, 2, 3],
          "best_times": ["07:30", "12:30"],
          "content_preferences": ["thought leadership", "case study", "industry news"]
        }},
        "instagram": {{
          "usage": "media - svago e ispirazione",
          "best_days": [4, 5, 6],
          "best_times": ["13:00", "21:00"],
          "content_preferences": ["behind the scenes", "tips veloci", "stories"]
        }},
        "facebook": {{
          "usage": "bassa-media",
          "best_days": [2, 5],
          "best_times": ["13:00"],
          "content_preferences": ["community", "eventi"]
        }},
        "newsletter": {{
          "usage": "alta - legge email professionali",
          "best_days": [1, 3],
          "best_times": ["07:00"],
          "content_preferences": ["approfondimenti", "trend", "risorse"]
        }}
      }},
      "pain_points": ["poco tempo", "necessità di efficienza", "..."],
      "interests": ["innovazione", "crescita aziendale", "..."],
      "buying_triggers": ["ROI dimostrabile", "case study simili", "..."],
      "weight": 0.6
    }}
  ],
  "recommended_posts_per_week": {{
    "linkedin": 3,
    "instagram": 5,
    "facebook": 2,
    "newsletter": 1,
    "blog": 1
  }},
  "frequency_rationale": "Spiegazione delle frequenze consigliate basata su brand e personas",
  "scheduling_strategy": {{
    "linkedin": {{
      "posts_distribution": "concentrare martedì-giovedì mattina",
      "avoid": ["weekend", "lunedì mattina", "venerdì pomeriggio"],
      "optimal_slots": [
        {{"day": 1, "time": "08:30", "priority": 1}},
        {{"day": 2, "time": "08:30", "priority": 2}},
        {{"day": 3, "time": "08:30", "priority": 3}}
      ]
    }},
    "instagram": {{
      "posts_distribution": "mix settimanale con weekend",
      "avoid": ["mattina presto feriali"],
      "optimal_slots": [
        {{"day": 0, "time": "12:00", "priority": 1}},
        {{"day": 4, "time": "19:00", "priority": 2}},
        {{"day": 6, "time": "12:00", "priority": 3}}
      ]
    }},
    "facebook": {{
      "posts_distribution": "metà settimana e weekend",
      "avoid": ["lunedì", "venerdì sera"],
      "optimal_slots": [
        {{"day": 2, "time": "13:00", "priority": 1}},
        {{"day": 5, "time": "10:00", "priority": 2}}
      ]
    }},
    "newsletter": {{
      "posts_distribution": "1-2 invii settimanali",
      "avoid": ["weekend", "lunedì", "venerdì"],
      "optimal_slots": [
        {{"day": 1, "time": "07:00", "priority": 1}},
        {{"day": 3, "time": "07:00", "priority": 2}}
      ]
    }},
    "blog": {{
      "posts_distribution": "1-2 articoli settimanali",
      "avoid": ["weekend"],
      "optimal_slots": [
        {{"day": 2, "time": "10:00", "priority": 1}},
        {{"day": 4, "time": "10:00", "priority": 2}}
      ]
    }},
    "google_business": {{
      "posts_distribution": "2-3 post settimanali",
      "avoid": [],
      "optimal_slots": [
        {{"day": 1, "time": "10:00", "priority": 1}},
        {{"day": 4, "time": "14:00", "priority": 2}}
      ]
    }}
  }},
  "analysis_notes": "Breve nota sulle scelte fatte e suggerimenti strategici"
}}
```

Rispondi SOLO con il JSON, senza markdown o altro testo.
"""


async def analyze_buyer_personas(
    brand_name: str,
    sector: str = None,
    description: str = None,
    target_audience: str = None,
    products_services: str = None,
    brand_values: list = None,
    tone_of_voice: str = None,
    url_context: str = None,
    platforms: list = None,
    objectives: list = None
) -> dict:
    """
    Analizza brand e genera buyer personas con scheduling ottimale.
    """
    logger.info(f"[PERSONA] Analyzing personas for brand: {brand_name}")
    
    client = anthropic.Anthropic(api_key=os.getenv("ANTHROPIC_API_KEY"))
    
    prompt = PERSONA_ANALYSIS_PROMPT.format(
        brand_name=brand_name,
        sector=sector or "Non specificato",
        description=description or "Non disponibile",
        target_audience=target_audience or "Non specificato",
        products_services=products_services or "Non specificati",
        brand_values=", ".join(brand_values) if brand_values else "Non specificati",
        tone_of_voice=tone_of_voice or "Non specificato",
        url_context=url_context or "Non disponibile",
        platforms=", ".join(platforms) if platforms else "Tutte"
    )
    
    try:
        response = client.messages.create(
            model="claude-sonnet-4-20250514",
            max_tokens=4000,
            messages=[{"role": "user", "content": prompt}]
        )
        
        content = response.content[0].text.strip()
        
        # Pulisci eventuale markdown
        if content.startswith("```"):
            content = content.split("```")[1]
            if content.startswith("json"):
                content = content[4:]
        content = content.strip()
        
        personas_data = json.loads(content)
        personas_data["generated_at"] = datetime.now().isoformat()
        personas_data["source"] = "ai_analysis"
        
        logger.info(f"[PERSONA] Generated {len(personas_data.get('personas', []))} personas")
        
        # STEP 2: Arricchisci scheduling con Perplexity
        if platforms:
            logger.info("[PERSONA] Enriching schedule with Perplexity research...")
            try:
                # Determina B2B o B2C
                business_type = "B2B" if sector and any(x in sector.lower() for x in ["b2b", "business", "consulenza", "servizi professionali", "enterprise"]) else "B2C"
                
                # Costruisci descrizione di TUTTE le personas per analisi combinata
                all_personas = personas_data.get("personas", [])
                personas_descriptions = []
                for p in all_personas:
                    weight = p.get("weight", 0)
                    name = p.get("name", "Persona")
                    demo = p.get("demographics", {})
                    role = demo.get("role", "")
                    age = demo.get("age_range", "")
                    personas_descriptions.append(f"{name} ({role}, {age}, peso {weight*100:.0f}%)")
                
                combined_personas = " | ".join(personas_descriptions) if personas_descriptions else "Target generico"
                logger.info(f"[PERSONA] Analyzing schedule for ALL personas: {combined_personas}")
                
                # Ricerca per ogni piattaforma considerando TUTTE le personas
                for platform in platforms:
                    perplexity_schedule = await research_optimal_schedule(
                        business_type=business_type,
                        sector=sector or "generico",
                        platform=platform,
                        buyer_persona=combined_personas,
                        country="Italia",
                        objective=objectives[0] if objectives else "engagement"
                    )
                    
                    # Aggiorna scheduling_strategy con dati Perplexity
                    if perplexity_schedule.get("source") == "perplexity":
                        if "scheduling_strategy" not in personas_data:
                            personas_data["scheduling_strategy"] = {}
                        if platform not in personas_data["scheduling_strategy"]:
                            personas_data["scheduling_strategy"][platform] = {}
                        
                        personas_data["scheduling_strategy"][platform]["perplexity_data"] = perplexity_schedule
                        
                        # Crea optimal_slots dai dati Perplexity
                        best_days = perplexity_schedule.get("best_days_numbers", [1, 3])
                        best_times = perplexity_schedule.get("best_times", ["10:00", "14:00"])
                        
                        slots = []
                        for i, day in enumerate(best_days):
                            time = best_times[i] if i < len(best_times) else best_times[0]
                            slots.append({"day": day, "time": time, "priority": i + 1})
                        
                        personas_data["scheduling_strategy"][platform]["optimal_slots"] = slots
                        
                logger.info("[PERSONA] Perplexity enrichment completed")
            except Exception as e:
                logger.warning(f"[PERSONA] Perplexity enrichment failed: {e}")
        
        return personas_data
        
    except json.JSONDecodeError as e:
        logger.error(f"[PERSONA] JSON parse error: {e}")
        logger.error(f"[PERSONA] Raw content: {content[:500]}")
        return get_default_personas(platforms)
    except Exception as e:
        logger.error(f"[PERSONA] Error: {e}")
        return get_default_personas(platforms)


def get_default_personas(platforms: list = None,
    objectives: list = None) -> dict:
    """Personas di fallback se l'analisi AI fallisce"""
    return {
        "personas": [
            {
                "name": "Professionista Generico",
                "demographics": {"age_range": "30-50", "role": "Manager/Professionista"},
                "weight": 1.0
            }
        ],
        "scheduling_strategy": {
            "linkedin": {
                "optimal_slots": [
                    {"day": 1, "time": "08:30", "priority": 1},
                    {"day": 3, "time": "08:30", "priority": 2}
                ]
            },
            "instagram": {
                "optimal_slots": [
                    {"day": 0, "time": "12:00", "priority": 1},
                    {"day": 4, "time": "19:00", "priority": 2},
                    {"day": 6, "time": "12:00", "priority": 3}
                ]
            },
            "facebook": {
                "optimal_slots": [
                    {"day": 2, "time": "13:00", "priority": 1},
                    {"day": 5, "time": "10:00", "priority": 2}
                ]
            },
            "newsletter": {
                "optimal_slots": [
                    {"day": 1, "time": "07:00", "priority": 1}
                ]
            },
            "blog": {
                "optimal_slots": [
                    {"day": 2, "time": "10:00", "priority": 1}
                ]
            },
            "google_business": {
                "optimal_slots": [
                    {"day": 1, "time": "10:00", "priority": 1},
                    {"day": 4, "time": "14:00", "priority": 2}
                ]
            }
        },
        "generated_at": datetime.now().isoformat(),
        "source": "default_fallback"
    }


def get_scheduling_for_platform(personas_data: dict, platform: str) -> list:
    """
    Estrae gli slot ottimali per una piattaforma dalle personas.
    Ritorna lista di (day, time) ordinata per priorità.
    """
    strategy = personas_data.get("scheduling_strategy", {})
    platform_strategy = strategy.get(platform, {})
    slots = platform_strategy.get("optimal_slots", [])
    
    # Ordina per priorità
    sorted_slots = sorted(slots, key=lambda x: x.get("priority", 99))
    
    return [(s["day"], s["time"]) for s in sorted_slots]
