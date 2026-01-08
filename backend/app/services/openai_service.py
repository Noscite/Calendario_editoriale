from openai import OpenAI
from typing import Optional
import base64
from app.core.config import settings

class OpenAIService:
    def __init__(self):
        self.client = OpenAI(api_key=settings.OPENAI_API_KEY)
    
    async def generate_image(
        self,
        prompt: str,
        size: str = "1024x1024",
        style: str = "vivid"
    ) -> str:
        """Genera immagine con GPT Image o DALL-E 3"""
        
        # Prima generiamo un prompt dettagliato con GPT-4
        detailed_prompt = await self._enhance_prompt_with_gpt4(prompt)
        
        try:
            # Prova prima con gpt-image-1 (migliore per infografiche)
            response = self.client.images.generate(
                model="gpt-image-1",
                prompt=detailed_prompt,
                size=size,
                quality="high",
                n=1
            )
            
            # gpt-image-1 restituisce base64, dobbiamo convertirlo
            if hasattr(response.data[0], 'b64_json') and response.data[0].b64_json:
                # Salviamo come file temporaneo e restituiamo URL
                return f"data:image/png;base64,{response.data[0].b64_json}"
            else:
                return response.data[0].url
                
        except Exception as e:
            # Fallback a DALL-E 3
            print(f"gpt-image-1 failed, falling back to DALL-E 3: {e}")
            response = self.client.images.generate(
                model="dall-e-3",
                prompt=detailed_prompt,
                size=size,
                quality="standard",
                style=style,
                n=1
            )
            return response.data[0].url
    
    async def _enhance_prompt_with_gpt4(self, original_prompt: str) -> str:
        """Usa GPT-4 per creare un prompt dettagliato come ChatGPT"""
        try:
            response = self.client.chat.completions.create(
                model="gpt-4o",
                messages=[
                    {
                        "role": "system",
                        "content": """Sei un esperto di prompt engineering per generazione immagini AI.
Il tuo compito è trasformare descrizioni semplici in prompt dettagliati e professionali per DALL-E/GPT-Image.

REGOLE:
- Crea prompt in inglese
- Sii molto specifico su composizione, colori, stile, illuminazione
- Per post social media, crea layout tipo infografica quando appropriato
- Specifica lo stile artistico (fotorealistico, illustrazione, flat design, ecc.)
- NON includere mai testo/scritte nell'immagine a meno che non sia esplicitamente richiesto
- Rispondi SOLO con il prompt, niente altro"""
                    },
                    {
                        "role": "user", 
                        "content": f"Crea un prompt dettagliato per questa immagine social media:\n\n{original_prompt}"
                    }
                ],
                max_tokens=500,
                temperature=0.7
            )
            return response.choices[0].message.content.strip()
        except Exception as e:
            print(f"GPT-4 prompt enhancement failed: {e}")
            return original_prompt
    
    async def generate_image_variation(
        self,
        image_url: str,
        prompt: str
    ) -> str:
        """Genera variazione di un'immagine esistente"""
        return await self.generate_image(prompt)
