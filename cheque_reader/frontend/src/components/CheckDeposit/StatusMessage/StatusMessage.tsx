import React from 'react';
import { CheckCircle, AlertCircle, Loader, Info } from 'lucide-react';
import { DepositStatus } from '../../../types';
import styles from './StatusMessage.module.css';

interface StatusMessageProps {
  status: DepositStatus;
}

export const StatusMessage: React.FC<StatusMessageProps> = ({ status }) => {
  if (!status.message) {
    return null;
  }

  const getIcon = () => {
    switch (status.status) {
      case 'processing':
        return <Loader size={20} className={styles.spinner} />;
      case 'success':
        return <CheckCircle size={20} />;
      case 'error':
        return <AlertCircle size={20} />;
      default:
        return <Info size={20} />;
    }
  };

  const getAriaLabel = () => {
    switch (status.status) {
      case 'processing':
        return 'Processing';
      case 'success':
        return 'Success';
      case 'error':
        return 'Error';
      default:
        return 'Information';
    }
  };

  return (
    <div
      className={`${styles.statusMessage} ${styles[status.status]}`}
      role="alert"
      aria-label={getAriaLabel()}
    >
      {getIcon()}
      <span>{status.message}</span>
    </div>
  );
};