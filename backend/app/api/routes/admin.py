from fastapi import APIRouter, Depends, HTTPException, Request
from sqlalchemy.orm import Session
from sqlalchemy import desc
from typing import List, Optional
from pydantic import BaseModel, EmailStr
from datetime import datetime

from app.core.database import get_db
from app.core.security import get_current_user, get_password_hash
from app.models.user import User, Organization
from app.models.activity_log import ActivityLog

router = APIRouter()

# === SCHEMAS ===

class UserOut(BaseModel):
    id: int
    email: str
    full_name: Optional[str]
    role: str
    is_active: bool
    organization_id: Optional[int]
    organization_name: Optional[str] = None
    created_at: Optional[datetime]
    phone: Optional[str] = None
    company: Optional[str] = None
    address: Optional[str] = None
    city: Optional[str] = None
    country: Optional[str] = None
    vat_number: Optional[str] = None
    notes: Optional[str] = None
    
    class Config:
        from_attributes = True

class UserCreate(BaseModel):
    email: EmailStr
    full_name: str
    password: str
    role: str = "editor"
    organization_id: Optional[int] = None

class UserUpdate(BaseModel):
    full_name: Optional[str] = None
    role: Optional[str] = None
    is_active: Optional[bool] = None
    organization_id: Optional[int] = None

class OrgOut(BaseModel):
    id: int
    name: str
    slug: Optional[str]
    created_at: Optional[datetime]
    user_count: int = 0
    
    class Config:
        from_attributes = True

class ActivityOut(BaseModel):
    id: int
    user_id: int
    user_email: Optional[str] = None
    user_name: Optional[str] = None
    action: str
    entity_type: Optional[str]
    entity_id: Optional[int]
    entity_name: Optional[str]
    details: Optional[dict]
    created_at: datetime
    
    class Config:
        from_attributes = True

# === HELPERS ===

VALID_ROLES = ["superuser", "admin", "editor", "viewer"]

def require_admin(current_user: User = Depends(get_current_user)):
    """Richiede almeno ruolo admin"""
    if current_user.role not in ["superuser", "admin"]:
        raise HTTPException(status_code=403, detail="Admin access required")
    return current_user

def require_superuser(current_user: User = Depends(get_current_user)):
    """Richiede ruolo superuser"""
    if current_user.role != "superuser":
        raise HTTPException(status_code=403, detail="Superuser access required")
    return current_user

def log_activity(
    db: Session,
    user: User,
    action: str,
    entity_type: str = None,
    entity_id: int = None,
    entity_name: str = None,
    details: dict = None,
    request: Request = None
):
    log = ActivityLog(
        user_id=user.id,
        organization_id=user.organization_id or 0,
        action=action,
        entity_type=entity_type,
        entity_id=entity_id,
        entity_name=entity_name,
        details=details,
        ip_address=request.client.host if request else None,
        user_agent=request.headers.get("user-agent") if request else None
    )
    db.add(log)
    db.commit()

# === ORGANIZATIONS (solo superuser) ===

@router.get("/organizations", response_model=List[OrgOut])
def list_organizations(
    db: Session = Depends(get_db),
    current_user: User = Depends(require_superuser)
):
    """Lista tutte le organizzazioni (solo superuser)"""
    orgs = db.query(Organization).all()
    result = []
    for org in orgs:
        user_count = db.query(User).filter(User.organization_id == org.id).count()
        result.append({
            **org.__dict__,
            "user_count": user_count
        })
    return result

# === USER MANAGEMENT ===

@router.get("/users", response_model=List[UserOut])
def list_users(
    organization_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_admin)
):
    """Lista utenti - superuser vede tutti, admin solo sua org"""
    query = db.query(User)
    
    if current_user.role == "superuser":
        # Superuser può filtrare per org o vedere tutti
        if organization_id:
            query = query.filter(User.organization_id == organization_id)
    else:
        # Admin vede solo sua organizzazione
        query = query.filter(User.organization_id == current_user.organization_id)
    
    users = query.order_by(User.created_at.desc()).all()
    
    # Arricchisci con nome organizzazione
    result = []
    for user in users:
        org = db.query(Organization).filter(Organization.id == user.organization_id).first()
        result.append({
            "id": user.id,
            "email": user.email,
            "full_name": user.full_name,
            "role": user.role,
            "is_active": user.is_active,
            "organization_id": user.organization_id,
            "organization_name": org.name if org else None,
            "created_at": user.created_at,
            "phone": user.phone,
            "company": user.company,
            "address": user.address,
            "city": user.city,
            "country": user.country,
            "vat_number": user.vat_number,
            "notes": user.notes
        })
    return result

@router.post("/users", response_model=UserOut)
def create_user(
    user_data: UserCreate,
    request: Request,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_admin)
):
    """Crea nuovo utente"""
    # Check email unica
    existing = db.query(User).filter(User.email == user_data.email).first()
    if existing:
        raise HTTPException(status_code=400, detail="Email già registrata")
    
    # Valida ruolo
    if user_data.role not in VALID_ROLES:
        raise HTTPException(status_code=400, detail="Ruolo non valido")
    
    # Solo superuser può creare altri superuser
    if user_data.role == "superuser" and current_user.role != "superuser":
        raise HTTPException(status_code=403, detail="Solo superuser può creare superuser")
    
    # Determina organization_id
    if current_user.role == "superuser" and user_data.organization_id:
        org_id = user_data.organization_id
    else:
        org_id = current_user.organization_id
    
    user = User(
        email=user_data.email,
        full_name=user_data.full_name,
        hashed_password=get_password_hash(user_data.password),
        role=user_data.role,
        organization_id=org_id,
        is_active=True
    )
    db.add(user)
    db.commit()
    db.refresh(user)
    
    log_activity(db, current_user, "create", "user", user.id, user.email, request=request)
    
    return user

@router.put("/users/{user_id}", response_model=UserOut)
def update_user(
    user_id: int,
    user_data: UserUpdate,
    request: Request,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_admin)
):
    """Aggiorna utente"""
    if current_user.role == "superuser":
        user = db.query(User).filter(User.id == user_id).first()
    else:
        user = db.query(User).filter(
            User.id == user_id,
            User.organization_id == current_user.organization_id
        ).first()
    
    if not user:
        raise HTTPException(status_code=404, detail="Utente non trovato")
    
    # Non può modificare se stesso il ruolo
    if user_id == current_user.id and user_data.role and user_data.role != current_user.role:
        raise HTTPException(status_code=400, detail="Non puoi modificare il tuo ruolo")
    
    # Solo superuser può assegnare ruolo superuser
    if user_data.role == "superuser" and current_user.role != "superuser":
        raise HTTPException(status_code=403, detail="Solo superuser può assegnare ruolo superuser")
    
    if user_data.full_name is not None:
        user.full_name = user_data.full_name
    if user_data.role is not None:
        user.role = user_data.role
    if user_data.is_active is not None:
        user.is_active = user_data.is_active
    if user_data.organization_id is not None and current_user.role == "superuser":
        user.organization_id = user_data.organization_id
    
    db.commit()
    db.refresh(user)
    
    log_activity(db, current_user, "update", "user", user.id, user.email, 
                 details=user_data.dict(exclude_none=True), request=request)
    
    return user

@router.delete("/users/{user_id}")
def delete_user(
    user_id: int,
    request: Request,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_admin)
):
    """Disattiva utente"""
    if user_id == current_user.id:
        raise HTTPException(status_code=400, detail="Non puoi eliminare te stesso")
    
    if current_user.role == "superuser":
        user = db.query(User).filter(User.id == user_id).first()
    else:
        user = db.query(User).filter(
            User.id == user_id,
            User.organization_id == current_user.organization_id
        ).first()
    
    if not user:
        raise HTTPException(status_code=404, detail="Utente non trovato")
    
    user.is_active = False
    db.commit()
    
    log_activity(db, current_user, "delete", "user", user.id, user.email, request=request)
    
    return {"message": "Utente disattivato"}

# === ACTIVITY LOG ===

@router.get("/activity", response_model=List[ActivityOut])
def get_activity_log(
    limit: int = 50,
    entity_type: Optional[str] = None,
    user_id: Optional[int] = None,
    organization_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_admin)
):
    """Activity log - superuser vede tutto, admin solo sua org"""
    query = db.query(ActivityLog)
    
    if current_user.role != "superuser":
        query = query.filter(ActivityLog.organization_id == current_user.organization_id)
    elif organization_id:
        query = query.filter(ActivityLog.organization_id == organization_id)
    
    if entity_type:
        query = query.filter(ActivityLog.entity_type == entity_type)
    if user_id:
        query = query.filter(ActivityLog.user_id == user_id)
    
    logs = query.order_by(desc(ActivityLog.created_at)).limit(limit).all()
    
    # Arricchisci con info utente
    result = []
    for log in logs:
        user = db.query(User).filter(User.id == log.user_id).first()
        result.append({
            **log.__dict__,
            "user_email": user.email if user else None,
            "user_name": user.full_name if user else None
        })
    
    return result

@router.get("/stats")
def get_stats(
    organization_id: Optional[int] = None,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_admin)
):
    """Statistiche - superuser può vedere per org o totali"""
    from app.models.brand import Brand
    from app.models.project import Project
    from app.models.post import Post
    
    if current_user.role == "superuser":
        if organization_id:
            org_filter = organization_id
        else:
            # Totali globali
            users_count = db.query(User).filter(User.is_active == True).count()
            brands_count = db.query(Brand).count()
            projects_count = db.query(Project).count()
            posts_count = db.query(Post).count()
            orgs_count = db.query(Organization).count()
            
            return {
                "users": users_count,
                "brands": brands_count,
                "projects": projects_count,
                "posts": posts_count,
                "organizations": orgs_count
            }
    else:
        org_filter = current_user.organization_id
    
    users_count = db.query(User).filter(User.organization_id == org_filter, User.is_active == True).count()
    brands_count = db.query(Brand).filter(Brand.organization_id == org_filter).count()
    projects_count = db.query(Project).join(Brand).filter(Brand.organization_id == org_filter).count()
    posts_count = db.query(Post).join(Project).join(Brand).filter(Brand.organization_id == org_filter).count()
    
    return {
        "users": users_count,
        "brands": brands_count,
        "projects": projects_count,
        "posts": posts_count
    }
