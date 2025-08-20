import React from 'react';
import { Building2 } from 'lucide-react';
import styles from './Header.module.css';

export const Header: React.FC = () => {
  return (
    <header className={styles.header}>
      <div className={styles.headerContent}>
        <div className={styles.logo}>
          <Building2 size={32} />
          <h1>SecureBank</h1>
        </div>
        <nav>
          <span className={styles.navItem}>Mobile Deposit</span>
        </nav>
      </div>
    </header>
  );
};