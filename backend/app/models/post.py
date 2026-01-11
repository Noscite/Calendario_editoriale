from sqlalchemy import Column, Integer, String, Date, ForeignKey, JSON, Text, DateTime, Boolean
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from app.core.database import Base

class Post(Base):
    __tablename__ = "posts"
    
    id = Column(Integer, primary_key=True, index=True)
    project_id = Column(Integer, ForeignKey("projects.id", ondelete="CASCADE"), nullable=False)
    platform = Column(String(50), nullable=False)
    scheduled_date = Column(Date)
    scheduled_time = Column(String(10))
    title = Column(String(255))
    content = Column(Text)
    hashtags = Column(JSON, default=list)
    pillar = Column(String(100))
    post_type = Column(String(50))
    visual_prompt = Column(Text)
    visual_suggestion = Column(Text)
    call_to_action = Column(Text)
    cta = Column(Text)
    image_prompt = Column(Text)
    image_url = Column(Text)
    media_type = Column(String(20), default="image")  # image, video
    image_format = Column(String(20), default="1080x1080")  # 1080x1080, 1080x1920, 1920x1080
    carousel_images = Column(JSON, default=list)  # Array URL per carosello
    carousel_prompts = Column(JSON, default=list)  # Array prompt per carosello
    is_carousel = Column(Boolean, default=False)
    content_type = Column(String(20), default="post")  # post, story, reel
    status = Column(String(20), default="draft")
    created_at = Column(DateTime, server_default=func.now())
    
    publication_status = Column(String(50), default="draft")
    
    project = relationship("Project", back_populates="posts")
    publications = relationship("PostPublication", back_populates="post", cascade="all, delete-orphan")
