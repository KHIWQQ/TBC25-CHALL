import React from 'react';
import styles from './AmountInput.module.css';

interface AmountInputProps {
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
  error?: string;
}

export const AmountInput: React.FC<AmountInputProps> = ({
  value,
  onChange,
  disabled = false,
  error,
}) => {
  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const inputValue = e.target.value;
    
    if (/^\d*\.?\d{0,2}$/.test(inputValue) || inputValue === '') {
      onChange(inputValue);
    }
  };

  return (
    <div className={styles.amountInput}>
      <label htmlFor="amount" className={styles.label}>
        Check Amount ($)
      </label>
      <div className={styles.inputWrapper}>
        <span className={styles.dollarSign}>$</span>
        <input
          id="amount"
          type="text"
          inputMode="decimal"
          value={value}
          onChange={handleChange}
          placeholder="0.00"
          disabled={disabled}
          className={`${styles.input} ${error ? styles.inputError : ''}`}
          aria-describedby={error ? 'amount-error' : undefined}
        />
      </div>
      {error && (
        <div id="amount-error" className={styles.error} role="alert">
          {error}
        </div>
      )}
    </div>
  );
};
