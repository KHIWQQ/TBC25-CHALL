import React, { useRef } from 'react';
import { Upload, Camera } from 'lucide-react';
import styles from './ImageUpload.module.css';

interface ImageUploadProps {
  side: 'front' | 'back';
  title: string;
  image: File | null;
  preview: string | null;
  onImageUpload: (file: File) => void;
  disabled?: boolean;
}

export const ImageUpload: React.FC<ImageUploadProps> = ({
  side,
  title,
  image,
  preview,
  onImageUpload,
  disabled = false,
}) => {
  const inputRef = useRef<HTMLInputElement>(null);

  const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file) {
      onImageUpload(file);
    }
  };

  const handleClick = () => {
    if (!disabled) {
      inputRef.current?.click();
    }
  };

  const handleKeyDown = (event: React.KeyboardEvent) => {
    if ((event.key === 'Enter' || event.key === ' ') && !disabled) {
      event.preventDefault();
      inputRef.current?.click();
    }
  };

  return (
    <div className={styles.uploadGroup}>
      <h3 className={styles.title}>{title}</h3>
      <div className={styles.uploadArea}>
        {preview ? (
          <div className={styles.imagePreview}>
            <img src={preview} alt={`Check ${side}`} className={styles.previewImage} />
            <button
              className={styles.retakeBtn}
              onClick={handleClick}
              disabled={disabled}
              aria-label={`Retake ${side} image`}
            >
              <Camera size={16} />
              Retake
            </button>
          </div>
        ) : (
          <div
            className={`${styles.uploadPlaceholder} ${disabled ? styles.disabled : ''}`}
            onClick={handleClick}
            onKeyDown={handleKeyDown}
            tabIndex={disabled ? -1 : 0}
            role="button"
            aria-label={`Upload ${side} image`}
          >
            <Upload size={48} />
            <p>Click to upload {side} of check</p>
            <span>JPG, PNG up to 10MB</span>
          </div>
        )}
        <input
          ref={inputRef}
          type="file"
          accept="image/*"
          onChange={handleFileChange}
          style={{ display: 'none' }}
          disabled={disabled}
          aria-label={`${title} file input`}
        />
      </div>
    </div>
  );
};