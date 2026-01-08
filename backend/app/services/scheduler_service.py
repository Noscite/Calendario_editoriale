import asyncio
import logging
from datetime import datetime, date, timezone
from sqlalchemy.orm import Session
from sqlalchemy import and_
from app.core.database import SessionLocal
from app.models.post import Post
from app.models.social_connection import SocialConnection, PostPublication
from app.models.project import Project
from app.models.brand import Brand
from app.services.publisher_service import publisher_service

logger = logging.getLogger(__name__)

async def check_and_publish_posts():
    """Controlla e pubblica i post programmati per ora"""
    db = SessionLocal()
    try:
        now = datetime.now()
        current_date = now.date()
        current_time = now.strftime("%H:%M")
        
        logger.info(f"Scheduler check: {current_date} {current_time}")
        
        # Trova post da pubblicare
        posts = db.query(Post).filter(
            Post.scheduled_date == current_date,
            Post.scheduled_time.like(current_time + '%'),
            Post.publication_status.in_(["scheduled", "pending", "draft"])
        ).all()
        
        logger.info(f"Found {len(posts)} posts to publish")
        
        for post in posts:
            await publish_single_post(db, post)
        
        db.commit()
        
    except Exception as e:
        logger.error(f"Scheduler error: {e}")
        db.rollback()
    finally:
        db.close()

async def publish_single_post(db: Session, post: Post):
    """Pubblica un singolo post su tutte le piattaforme configurate"""
    try:
        # Trova il brand tramite il project
        project = db.query(Project).filter(Project.id == post.project_id).first()
        if not project:
            logger.error(f"Project not found for post {post.id}")
            return
        
        # Trova connessione social per la piattaforma del post
        connection = db.query(SocialConnection).filter(
            SocialConnection.brand_id == project.brand_id,
            SocialConnection.platform == post.platform,
            SocialConnection.is_active == True
        ).first()
        
        if not connection:
            logger.warning(f"No active connection for {post.platform} on brand {project.brand_id}")
            post.publication_status = "failed"
            return
        
        # Pubblica
        logger.info(f"Publishing post {post.id} to {post.platform}")
        result = await publisher_service.publish_post(post, connection)
        
        # Aggiorna stato
        if result["success"]:
            post.publication_status = "published"
            
            # Crea record pubblicazione
            publication = PostPublication(
                post_id=post.id,
                social_connection_id=connection.id,
                status="published",
                published_at=datetime.now(timezone.utc).replace(tzinfo=None),
                external_post_id=result.get("external_post_id"),
                external_post_url=result.get("external_post_url")
            )
            db.merge(publication)
            logger.info(f"Post {post.id} published successfully: {result.get('external_post_url')}")
        else:
            post.publication_status = "failed"
            
            publication = PostPublication(
                post_id=post.id,
                social_connection_id=connection.id,
                status="failed",
                error_message=result.get("error", "Unknown error")
            )
            db.merge(publication)
            logger.error(f"Post {post.id} failed: {result.get('error')}")
        
        connection.last_used_at = datetime.now(timezone.utc).replace(tzinfo=None)
        
    except Exception as e:
        logger.error(f"Error publishing post {post.id}: {e}")
        post.publication_status = "failed"

async def run_scheduler():
    """Loop principale dello scheduler"""
    logger.info("Scheduler started")
    while True:
        await check_and_publish_posts()
        await asyncio.sleep(60)  # Controlla ogni minuto

if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    asyncio.run(run_scheduler())
