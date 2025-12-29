from fastapi import APIRouter, Depends, HTTPException, status
from fastapi.security import OAuth2PasswordRequestForm
from sqlalchemy.orm import Session
from datetime import timedelta
from app.core.database import get_db
from app.core.config import settings
from app.core.security import verify_password, get_password_hash, create_access_token, get_current_user
from app.models.user import User
from app.schemas.user import UserCreate, UserResponse, Token
from pydantic import BaseModel
from typing import Optional

router = APIRouter()

@router.post("/register", response_model=UserResponse)
def register(user_data: UserCreate, db: Session = Depends(get_db)):
    # Check if user exists
    if db.query(User).filter(User.email == user_data.email).first():
        raise HTTPException(status_code=400, detail="Email già registrata")
    
    # Create user
    user = User(
        email=user_data.email,
        hashed_password=get_password_hash(user_data.password),
        full_name=user_data.full_name
    )
    db.add(user)
    db.commit()
    db.refresh(user)
    return user

@router.post("/login", response_model=Token)
def login(form_data: OAuth2PasswordRequestForm = Depends(), db: Session = Depends(get_db)):
    user = db.query(User).filter(User.email == form_data.username).first()
    if not user or not verify_password(form_data.password, user.hashed_password):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Email o password non corretti",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    access_token = create_access_token(
        data={"sub": user.email},
        expires_delta=timedelta(minutes=settings.ACCESS_TOKEN_EXPIRE_MINUTES)
    )
    return {"access_token": access_token, "token_type": "bearer"}

@router.get("/me")
def get_me(current_user: User = Depends(get_current_user), db: Session = Depends(get_db)):
    from app.models.user import Organization
    org = db.query(Organization).filter(Organization.id == current_user.organization_id).first()
    return {
        "id": current_user.id,
        "email": current_user.email,
        "full_name": current_user.full_name,
        "role": current_user.role,
        "is_active": current_user.is_active,
        "organization_id": current_user.organization_id,
        "organization_name": org.name if org else None,
        "phone": current_user.phone,
        "company": current_user.company,
        "address": current_user.address,
        "city": current_user.city,
        "country": current_user.country,
        "vat_number": current_user.vat_number,
        "notes": current_user.notes
    }


# === PROFILE ENDPOINTS ===

class ProfileUpdate(BaseModel):
    full_name: Optional[str] = None
    phone: Optional[str] = None
    company: Optional[str] = None
    address: Optional[str] = None
    city: Optional[str] = None
    country: Optional[str] = None
    vat_number: Optional[str] = None
    notes: Optional[str] = None

class PasswordChange(BaseModel):
    current_password: str
    new_password: str

@router.put("/profile")
def update_profile(
    profile_data: ProfileUpdate,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Aggiorna profilo utente"""
    if profile_data.full_name is not None:
        current_user.full_name = profile_data.full_name
    if profile_data.phone is not None:
        current_user.phone = profile_data.phone
    if profile_data.company is not None:
        current_user.company = profile_data.company
    if profile_data.address is not None:
        current_user.address = profile_data.address
    if profile_data.city is not None:
        current_user.city = profile_data.city
    if profile_data.country is not None:
        current_user.country = profile_data.country
    if profile_data.vat_number is not None:
        current_user.vat_number = profile_data.vat_number
    if profile_data.notes is not None:
        current_user.notes = profile_data.notes
    
    db.commit()
    db.refresh(current_user)
    
    return {"message": "Profilo aggiornato"}

@router.post("/change-password")
def change_password(
    password_data: PasswordChange,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_user)
):
    """Cambia password utente"""
    from app.core.security import verify_password, get_password_hash
    
    # Verifica password attuale
    if not verify_password(password_data.current_password, current_user.hashed_password):
        raise HTTPException(status_code=400, detail="Password attuale non corretta")
    
    # Valida nuova password
    if len(password_data.new_password) < 8:
        raise HTTPException(status_code=400, detail="La password deve essere di almeno 8 caratteri")
    
    # Aggiorna password
    current_user.hashed_password = get_password_hash(password_data.new_password)
    db.commit()
    
    return {"message": "Password cambiata con successo"}
