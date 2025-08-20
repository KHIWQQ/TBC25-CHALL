import React from 'react';
import styles from './RemarksInput.module.css';

interface RemarksInputProps {
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
}

export const RemarksInput: React.FC<RemarksInputProps> = ({
  value,
  onChange,
  disabled = false,
}) => {
  return (
    <div className={styles.remarksContainer}>
      <label htmlFor="remarks" className={styles.label}>
        Remarks (Optional)
      </label>
      <textarea
        id="remarks"
        className={styles.textarea}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder="Add any additional notes about this deposit..."
        disabled={disabled}
        rows={3}
      />
    </div>
  );
}; 