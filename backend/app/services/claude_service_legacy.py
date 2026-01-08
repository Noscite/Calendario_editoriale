"""
Legacy functions for single post regeneration and image prompts.
"""

import anthropic
import json
import os

client = anthropic.Anthropic(api_key=os.getenv("ANTHROPIC_API_KEY"))


def regenerate_single_post(
    post_content: str,
    platform: str,
    pillar: str,
    user_prompt: str,
    brand_context: str = "",
    tone_of_voice: str = "",
    brand_style_guide: str = ""
) -> dict:
    """Rigenera un singolo post basandosi sul feedback utente"""
    
    system_prompt = f"""Sei un esperto social media manager.
Devi rigenerare un post per {platform} seguendo le indicazioni dell'utente.

BRAND: {brand_context}
TONO DI VOCE: {tone_of_voice}
PILLAR: {pillar}

{f'STYLE GUIDE: {brand_style_guide}' if brand_style_guide else ''}

Rispondi SOLO con un JSON valido:
{{
    "content": "testo del post",
    "hashtags": ["hashtag1", "hashtag2"],
    "visual_suggestion": "suggerimento visivo",
    "cta": "call to action"
}}"""

    try:
        response = client.messages.create(
            model="claude-sonnet-4-20250514",
            max_tokens=1000,
            messages=[{"role": "user", "content": f"POST ORIGINALE:\n{post_content}\n\nRICHIESTA: {user_prompt}"}],
            system=system_prompt
        )
        
        text = response.content[0].text.strip()
        if "```json" in text:
            text = text.split("```json")[1].split("```")[0]
        elif "```" in text:
            text = text.split("```")[1].split("```")[0]
        
        return json.loads(text)
    except Exception as e:
        print(f"[REGEN] Error: {e}")
        return {"content": post_content, "hashtags": [], "visual_suggestion": "", "cta": ""}


def generate_image_prompt(
    post_content: str,
    platform: str,
    pillar: str,
    brand_name: str = "",
    brand_sector: str = "",
    brand_colors: str = "",
    visual_suggestion: str = ""
) -> str:
    """Genera prompt per immagine AI"""
    
    try:
        response = client.messages.create(
            model="claude-sonnet-4-20250514",
            max_tokens=500,
            messages=[{"role": "user", "content": f"Crea prompt immagine per {platform}.\nPOST: {post_content}\nBRAND: {brand_name}, {brand_sector}\nSTILE RICHIESTO: {visual_suggestion}"}],
            system="Genera un prompt in inglese per DALL-E. IMPORTANTE: NON includere MAI testo, scritte, parole o numeri nell'immagine. Solo elementi visivi. Solo il prompt, niente altro."
        )
        return response.content[0].text.strip()
    except Exception as e:
        return f"Professional social media image for {brand_name}"
