from pydantic import BaseModel
from typing import Optional, List, Any
from datetime import date, time, datetime

class PostBase(BaseModel):
    platform: str
    scheduled_date: date
    scheduled_time: Optional[time] = None
    title: Optional[str] = None
    content: str
    hashtags: Optional[List[str]] = None
    visual_prompt: Optional[str] = None
    visual_suggestion: Optional[str] = None
    pillar: Optional[str] = None
    post_type: Optional[str] = None
    cta: Optional[str] = None

class PostCreate(PostBase):
    project_id: int

class PostUpdate(BaseModel):
    content: Optional[str] = None
    title: Optional[str] = None
    hashtags: Optional[List[str]] = None
    scheduled_date: Optional[date] = None
    scheduled_time: Optional[time] = None
    visual_prompt: Optional[str] = None
    visual_suggestion: Optional[str] = None
    status: Optional[str] = None
    pillar: Optional[str] = None
    cta: Optional[str] = None

class PostResponse(PostBase):
    id: int
    project_id: int
    status: str
    image_url: Optional[str] = None
    media_type: Optional[str] = None
    content_type: Optional[str] = None
    publication_status: Optional[str] = None
    created_at: Optional[datetime] = None
    
    class Config:
        from_attributes = True
