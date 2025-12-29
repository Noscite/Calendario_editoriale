from sqlalchemy import Column, Integer, String, DateTime, ForeignKey, JSON, Text
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from app.core.database import Base

class ActivityLog(Base):
    __tablename__ = "activity_logs"
    
    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    organization_id = Column(Integer, ForeignKey("organizations.id"), nullable=False)
    
    action = Column(String(50), nullable=False)  # create, update, delete, generate, export, login
    entity_type = Column(String(50))  # brand, project, post, user
    entity_id = Column(Integer)
    entity_name = Column(String(255))
    
    details = Column(JSON)  # Dettagli aggiuntivi
    ip_address = Column(String(45))
    user_agent = Column(Text)
    
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    
    user = relationship("User")
