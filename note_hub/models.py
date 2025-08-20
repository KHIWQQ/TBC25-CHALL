from contextlib import contextmanager

from sqlalchemy import Boolean, Column, ForeignKey, Integer, String, Text, create_engine
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import relationship, scoped_session, sessionmaker
from sqlalchemy.pool import NullPool

Base = declarative_base()

# Configure SQLAlchemy engine with optimized settings for high traffic
engine = create_engine(
    "postgresql+psycopg2://postgres:postgres@db/note_hub",
    poolclass=NullPool,
    echo=False,
)

# Create a scoped session to handle thread safety
session_factory = sessionmaker(bind=engine, expire_on_commit=False)
Session = scoped_session(session_factory)


@contextmanager
def session_scope():
    """Provide a transactional scope around a series of operations."""
    session = Session()
    try:
        yield session
        session.commit()
    except:
        session.rollback()
        raise
    finally:
        session.close()


class User(Base):
    __tablename__ = "users"

    id = Column(Integer, primary_key=True)
    username = Column(String, unique=True, nullable=False)
    password = Column(String, nullable=False)

    notes = relationship("Note", back_populates="user", cascade="all, delete-orphan")

    def __repr__(self):
        return f"<User(username='{self.username}')>"


class Note(Base):
    __tablename__ = "notes"

    id = Column(Integer, primary_key=True)
    uuid = Column(String, unique=True, nullable=False)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    title = Column(String, nullable=False)
    content = Column(Text, nullable=False)
    is_public = Column(Boolean, default=False, nullable=False)

    user = relationship("User", back_populates="notes")

    def __repr__(self):
        return f"<Note(title='{self.title}', user_id={self.user_id})>"


def init_db():
    """Initialize the database by creating all tables."""
    Base.metadata.create_all(engine)
