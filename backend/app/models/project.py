from sqlalchemy import Column, Integer, String, Date, ForeignKey, Enum, JSON, Text
from sqlalchemy.orm import relationship
import enum
from app.core.database import Base

class ProjectStatus(str, enum.Enum):
    draft = "draft"
    generating = "generating"
    review = "review"
    approved = "approved"
    published = "published"

class Project(Base):
    __tablename__ = "projects"
    
    id = Column(Integer, primary_key=True, index=True)
    brand_id = Column(Integer, ForeignKey("brands.id"), nullable=False)
    name = Column(String(255), nullable=False)
    start_date = Column(Date, nullable=False)
    end_date = Column(Date, nullable=False)
    platforms = Column(JSON, default=list)
    posts_per_week = Column(JSON, default=dict)
    themes = Column(JSON, default=list)
    brief = Column(Text)
    custom_prompt = Column(Text)
    status = Column(Enum(ProjectStatus), default=ProjectStatus.draft)
    
    # Nuovi campi
    reference_urls = Column(JSON, default=list)
    target_audience = Column(Text)
    objectives = Column(JSON, default=list)  # lead_generation, brand_awareness, engagement, sales, traffic
    content_pillars = Column(JSON, default=list)
    competitors = Column(JSON, default=list)
    special_dates = Column(JSON, default=list)
    buyer_personas = Column(JSON, default=None)  # AI-generated personas
    
    brand = relationship("Brand", back_populates="projects")
    posts = relationship("Post", back_populates="project", cascade="all, delete-orphan")
