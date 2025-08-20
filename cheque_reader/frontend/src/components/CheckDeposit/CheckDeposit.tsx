import React, { useState } from 'react';
import { Loader } from 'lucide-react';
import { useImageUpload } from '../../hooks/useImageUpload';
import { useCheckDeposit } from '../../hooks/useCheckDeposit';
import { AmountInput } from './AmountInput/AmountInput';
import { ImageUpload } from './ImageUpload/ImageUpload';
import { StatusMessage } from './StatusMessage/StatusMessage';
import { RemarksInput } from './RemarksInput/RemarksInput';
import styles from './CheckDeposit.module.css';

export const CheckDeposit: React.FC = () => {
  const [amount, setAmount] = useState('');
  const [remarks, setRemarks] = useState('');
  const { images, previews, handleImageUpload, clearImages } = useImageUpload();
  const { depositStatus, submitDeposit, resetStatus } = useCheckDeposit();

  const handleSubmit = async () => {
    const result = await submitDeposit(amount, images.front, images.back, remarks);
    
    if (result?.success) {
      setAmount('');
      setRemarks('');
      clearImages();
    }
  };

  const handleReset = () => {
    setAmount('');
    setRemarks('');
    clearImages();
    resetStatus();
  };

  const isProcessing = depositStatus.status === 'processing';
  const isSuccess = depositStatus.status === 'success';
  const canSubmit = images.front && images.back && amount && !isProcessing;

  return (
    <div className={styles.checkDeposit}>
      <div className={styles.depositCard}>
        <header className={styles.header}>
          <h2>Mobile Check Deposit</h2>
          <p className={styles.subtitle}>
            Deposit your check by taking photos of the front and back
          </p>
        </header>

        <form onSubmit={(e) => e.preventDefault()}>
          <AmountInput
            value={amount}
            onChange={setAmount}
            disabled={isProcessing}
          />

          <RemarksInput
            value={remarks}
            onChange={setRemarks}
            disabled={isProcessing}
          />

          <div className={styles.imageUploadSection}>
            <ImageUpload
              side="front"
              title="Front of Check"
              image={images.front}
              preview={previews.front}
              onImageUpload={(file) => handleImageUpload('front', file)}
              disabled={isProcessing}
            />

            <ImageUpload
              side="back"
              title="Back of Check"
              image={images.back}
              preview={previews.back}
              onImageUpload={(file) => handleImageUpload('back', file)}
              disabled={isProcessing}
            />
          </div>

          <StatusMessage status={depositStatus} />

          <div className={styles.actionButtons}>
            {isSuccess ? (
              <button
                type="button"
                className={`${styles.btn} ${styles.btnSecondary}`}
                onClick={handleReset}
              >
                Make Another Deposit
              </button>
            ) : (
              <>
                <button
                  type="button"
                  className={`${styles.btn} ${styles.btnSecondary}`}
                  onClick={handleReset}
                  disabled={isProcessing}
                >
                  Clear
                </button>
                <button
                  type="button"
                  className={`${styles.btn} ${styles.btnPrimary}`}
                  onClick={handleSubmit}
                  disabled={!canSubmit}
                >
                  {isProcessing ? (
                    <>
                      <Loader size={16} className={styles.spinner} />
                      Processing...
                    </>
                  ) : (
                    'Deposit Check'
                  )}
                </button>
              </>
            )}
          </div>
        </form>

        <div className={styles.securityNotice}>
          <p>
            🔒 Your images are encrypted and processed securely. Please destroy
            the physical check after successful deposit.
          </p>
        </div>
      </div>
    </div>
  );
};
