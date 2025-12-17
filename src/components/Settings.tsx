import { useState } from 'react';
import { usePurchase } from '../hooks/usePurchase';
import './Settings.css';

interface SettingsProps {
  isOpen: boolean;
  onClose: () => void;
}

export function Settings({ isOpen, onClose }: SettingsProps) {
  const { adsRemoved, isLoading, error, price, purchase, restore, resetForTesting } =
    usePurchase();
  const [message, setMessage] = useState<string | null>(null);

  if (!isOpen) return null;

  const handlePurchase = async () => {
    setMessage(null);
    try {
      await purchase();
      setMessage('Purchase successful! Ads have been removed.');
    } catch {
      // Error is already set in usePurchase
    }
  };

  const handleRestore = async () => {
    setMessage(null);
    try {
      const restored = await restore();
      if (restored) {
        setMessage('Purchase restored! Ads have been removed.');
      }
    } catch {
      // Error is already set in usePurchase
    }
  };

  const handleReset = () => {
    resetForTesting();
    setMessage('Purchase state reset (dev only)');
  };

  const isDev = import.meta.env.VITE_ENV !== 'production';

  return (
    <div className="settings-overlay" onClick={onClose}>
      <div className="settings-modal" onClick={(e) => e.stopPropagation()}>
        <div className="settings-header">
          <h2>Settings</h2>
          <button className="close-button" onClick={onClose} aria-label="Close">
            &times;
          </button>
        </div>

        <div className="settings-content">
          <div className="settings-section">
            <h3>Ad Settings</h3>

            {adsRemoved ? (
              <div className="purchase-status purchased">
                Ads have been removed. Thank you for your purchase!
              </div>
            ) : (
              <>
                <p className="settings-description">
                  Remove ads permanently with a one-time purchase.
                </p>

                <button
                  className="purchase-button"
                  onClick={handlePurchase}
                  disabled={isLoading}
                >
                  {isLoading
                    ? 'Processing...'
                    : price
                    ? `Remove Ads (${price})`
                    : 'Remove Ads'}
                </button>

                <button
                  className="restore-button"
                  onClick={handleRestore}
                  disabled={isLoading}
                >
                  {isLoading ? 'Processing...' : 'Restore Purchase'}
                </button>
              </>
            )}

            {error && <div className="error-message">{error}</div>}
            {message && <div className="success-message">{message}</div>}

            {isDev && (
              <div className="dev-section">
                <hr />
                <p className="dev-label">Development Only:</p>
                <button className="dev-button" onClick={handleReset}>
                  Reset Purchase State
                </button>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
