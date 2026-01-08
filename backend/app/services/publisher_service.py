import httpx
import os
import logging
from datetime import datetime, timezone
from sqlalchemy.orm import Session
from app.models.post import Post
from app.models.social_connection import SocialConnection, PostPublication
from app.models.project import Project
from app.models.brand import Brand

logger = logging.getLogger(__name__)

class PublisherService:
    
    async def publish_to_linkedin(self, post: Post, connection: SocialConnection) -> dict:
        """Pubblica su LinkedIn"""
        async with httpx.AsyncClient(timeout=60.0) as client:
            # Prepara contenuto
            content = post.content
            if post.hashtags:
                hashtags = " ".join([f"#{h}" if not h.startswith("#") else h for h in post.hashtags])
                content = f"{content}\n\n{hashtags}"
            
            # Determina se pubblicare come persona o organizzazione
            if connection.account_type == "organization":
                author = f"urn:li:organization:{connection.external_account_id}"
            else:
                author = f"urn:li:person:{connection.external_account_id}"
            
            headers = {
                "Authorization": f"Bearer {connection.access_token}",
                "Content-Type": "application/json",
                "X-Restli-Protocol-Version": "2.0.0"
            }
            
            media_asset = None
            
            # Se c'è un'immagine, uploadala
            if post.image_url:
                try:
                    # Step 1: Registra upload
                    register_payload = {
                        "registerUploadRequest": {
                            "recipes": ["urn:li:digitalmediaRecipe:feedshare-image"],
                            "owner": author,
                            "serviceRelationships": [{
                                "relationshipType": "OWNER",
                                "identifier": "urn:li:userGeneratedContent"
                            }]
                        }
                    }
                    
                    register_response = await client.post(
                        "https://api.linkedin.com/v2/assets?action=registerUpload",
                        json=register_payload,
                        headers=headers
                    )
                    
                    if register_response.status_code not in [200, 201]:
                        logger.error(f"LinkedIn register upload error: {register_response.text}")
                    else:
                        register_data = register_response.json()
                        upload_url = register_data["value"]["uploadMechanism"]["com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest"]["uploadUrl"]
                        media_asset = register_data["value"]["asset"]
                        
                        # Step 2: Upload immagine
                        image_path = f"/var/www/noscite-calendar/backend{post.image_url}"
                        
                        if os.path.exists(image_path):
                            with open(image_path, "rb") as f:
                                image_data = f.read()
                            
                            upload_response = await client.put(
                                upload_url,
                                content=image_data,
                                headers={
                                    "Authorization": f"Bearer {connection.access_token}",
                                    "Content-Type": "application/octet-stream"
                                }
                            )
                            
                            if upload_response.status_code not in [200, 201]:
                                logger.error(f"LinkedIn upload image error: {upload_response.status_code}")
                                media_asset = None
                        else:
                            logger.error(f"Image file not found: {image_path}")
                            media_asset = None
                            
                except Exception as e:
                    logger.error(f"LinkedIn image upload error: {str(e)}")
                    media_asset = None
            
            # Step 3: Crea il post
            if media_asset:
                payload = {
                    "author": author,
                    "lifecycleState": "PUBLISHED",
                    "specificContent": {
                        "com.linkedin.ugc.ShareContent": {
                            "shareCommentary": {"text": content},
                            "shareMediaCategory": "IMAGE",
                            "media": [{
                                "status": "READY",
                                "media": media_asset
                            }]
                        }
                    },
                    "visibility": {"com.linkedin.ugc.MemberNetworkVisibility": "PUBLIC"}
                }
            else:
                payload = {
                    "author": author,
                    "lifecycleState": "PUBLISHED",
                    "specificContent": {
                        "com.linkedin.ugc.ShareContent": {
                            "shareCommentary": {"text": content},
                            "shareMediaCategory": "NONE"
                        }
                    },
                    "visibility": {"com.linkedin.ugc.MemberNetworkVisibility": "PUBLIC"}
                }
            
            response = await client.post(
                "https://api.linkedin.com/v2/ugcPosts",
                json=payload,
                headers=headers
            )
            
            if response.status_code in [200, 201]:
                result = response.json()
                post_id = result.get("id", "")
                return {
                    "success": True,
                    "external_post_id": post_id,
                    "external_post_url": f"https://www.linkedin.com/feed/update/{post_id}"
                }
            else:
                logger.error(f"LinkedIn publish error: {response.status_code} - {response.text}")
                return {"success": False, "error": response.text}
    async def publish_to_facebook(self, post: Post, connection: SocialConnection) -> dict:
        """Pubblica su Facebook"""
        async with httpx.AsyncClient() as client:
            content = post.content
            if post.hashtags:
                hashtags = " ".join([f"#{h}" if not h.startswith("#") else h for h in post.hashtags])
                content = f"{content}\n\n{hashtags}"
            
            page_id = connection.external_account_id
            
            if post.image_url:
                # Post con immagine
                response = await client.post(
                    f"https://graph.facebook.com/v18.0/{page_id}/photos",
                    data={
                        "caption": content,
                        "url": post.image_url,
                        "access_token": connection.access_token
                    }
                )
            else:
                # Post solo testo
                response = await client.post(
                    f"https://graph.facebook.com/v18.0/{page_id}/feed",
                    data={
                        "message": content,
                        "access_token": connection.access_token
                    }
                )
            
            result = response.json()
            
            if "id" in result:
                return {
                    "success": True,
                    "external_post_id": result["id"],
                    "external_post_url": f"https://facebook.com/{result['id']}"
                }
            else:
                logger.error(f"Facebook publish error: {result}")
                return {"success": False, "error": result.get("error", {}).get("message", "Unknown error")}
    
    async def publish_to_instagram(self, post: Post, connection: SocialConnection) -> dict:
        """Pubblica su Instagram"""
        if not post.image_url:
            return {"success": False, "error": "Instagram richiede un'immagine"}
        
        async with httpx.AsyncClient() as client:
            ig_account_id = connection.external_account_id
            
            content = post.content
            if post.hashtags:
                hashtags = " ".join([f"#{h}" if not h.startswith("#") else h for h in post.hashtags])
                content = f"{content}\n\n{hashtags}"
            
            # Step 1: Crea media container
            container_response = await client.post(
                f"https://graph.facebook.com/v18.0/{ig_account_id}/media",
                data={
                    "image_url": f"https://calendar.noscite.it{post.image_url}" if post.image_url.startswith("/") else post.image_url,
                    "caption": content,
                    "access_token": connection.access_token
                }
            )
            container_result = container_response.json()
            
            if "id" not in container_result:
                logger.error(f"Instagram container error: {container_result}")
                return {"success": False, "error": container_result.get("error", {}).get("message", "Container creation failed")}
            
            container_id = container_result["id"]
            
            # Step 2: Pubblica il container
            publish_response = await client.post(
                f"https://graph.facebook.com/v18.0/{ig_account_id}/media_publish",
                data={
                    "creation_id": container_id,
                    "access_token": connection.access_token
                }
            )
            publish_result = publish_response.json()
            
            if "id" in publish_result:
                return {
                    "success": True,
                    "external_post_id": publish_result["id"],
                    "external_post_url": f"https://instagram.com/p/{publish_result['id']}"
                }
            else:
                logger.error(f"Instagram publish error: {publish_result}")
                return {"success": False, "error": publish_result.get("error", {}).get("message", "Publish failed")}
    
    async def publish_post(self, post: Post, connection: SocialConnection) -> dict:
        """Pubblica un post sulla piattaforma appropriata"""
        platform = connection.platform
        
        if platform == "linkedin":
            return await self.publish_to_linkedin(post, connection)
        elif platform == "facebook":
            return await self.publish_to_facebook(post, connection)
        elif platform == "instagram":
            return await self.publish_to_instagram(post, connection)
        else:
            return {"success": False, "error": f"Piattaforma {platform} non supportata"}


publisher_service = PublisherService()
