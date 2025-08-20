import React from 'react';
import { Header } from './components/Header/Header';
import { CheckDeposit } from './components/CheckDeposit/CheckDeposit';
import './App.css';

function App() {
  return (
    <div className="App">
      <Header />
      <main className="main-content">
        <CheckDeposit />
      </main>
    </div>
  );
}

export default App;