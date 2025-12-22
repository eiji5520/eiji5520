import { useEffect } from 'react';
import { useStore } from './stores/useStore';
import Header from './components/Header';
import TabNavigation from './components/TabNavigation';
import ProductInput from './components/ProductInput';
import PostGenerator from './components/PostGenerator';
import PostManager from './components/PostManager';
import Settings from './components/Settings';

function App() {
  const { activeTab, initialize } = useStore();

  useEffect(() => {
    initialize();
  }, [initialize]);

  return (
    <div className="min-h-screen bg-gray-50">
      <Header />
      <TabNavigation />
      <main className="container mx-auto px-4 py-6 max-w-6xl">
        {activeTab === 'input' && <ProductInput />}
        {activeTab === 'generate' && <PostGenerator />}
        {activeTab === 'manage' && <PostManager />}
        {activeTab === 'settings' && <Settings />}
      </main>
    </div>
  );
}

export default App;
