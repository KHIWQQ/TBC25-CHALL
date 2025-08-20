import { useState, useCallback } from 'react';
import { CheckImages, ImagePreviews } from '../types';

export const useImageUpload = () => {
  const [images, setImages] = useState<CheckImages>({ front: null, back: null });
  const [previews, setPreviews] = useState<ImagePreviews>({ front: null, back: null });

  const handleImageUpload = useCallback((side: 'front' | 'back', file: File) => {
    setImages(prev => ({ ...prev, [side]: file }));

    const reader = new FileReader();
    reader.onload = (e) => {
      setPreviews(prev => ({
        ...prev,
        [side]: e.target?.result as string,
      }));
    };
    reader.readAsDataURL(file);
  }, []);

  const clearImages = useCallback(() => {
    setImages({ front: null, back: null });
    setPreviews({ front: null, back: null });
  }, []);

  const clearImage = useCallback((side: 'front' | 'back') => {
    setImages(prev => ({ ...prev, [side]: null }));
    setPreviews(prev => ({ ...prev, [side]: null }));
  }, []);

  return {
    images,
    previews,
    handleImageUpload,
    clearImages,
    clearImage,
  };
};