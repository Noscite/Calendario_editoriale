from pydantic import BaseModel
from typing import Optional, List, Dict, Any
from datetime import date, datetime

class ProjectBase(BaseModel):
    name: str
    start_date: date
    end_date: date
    platforms: List[str] = ["linkedin", "instagram"]
    posts_per_week: Dict[str, int] = {"linkedin": 3, "instagram": 4}
    themes: List[str] = []
    brief: Optional[str] = None
    reference_urls: List[str] = []
    target_audience: Optional[str] = None
    content_pillars: List[str] = []
    competitors: List[str] = []
    special_dates: List[dict] = []

class ProjectCreate(ProjectBase):
    brand_id: int

class ProjectUpdate(BaseModel):
    name: Optional[str] = None
    start_date: Optional[date] = None
    end_date: Optional[date] = None
    platforms: Optional[List[str]] = None
    posts_per_week: Optional[Dict[str, int]] = None
    themes: Optional[List[str]] = None
    brief: Optional[str] = None
    reference_urls: Optional[List[str]] = None
    target_audience: Optional[str] = None
    content_pillars: Optional[List[str]] = None
    competitors: Optional[List[str]] = None
    special_dates: Optional[List[dict]] = None
    status: Optional[str] = None
    buyer_personas: Optional[Dict[str, Any]] = None

class ProjectResponse(ProjectBase):
    id: int
    brand_id: int
    status: str
    created_at: datetime
    buyer_personas: Optional[Dict[str, Any]] = None
    objectives: Optional[List[str]] = None
    
    class Config:
        from_attributes = True
