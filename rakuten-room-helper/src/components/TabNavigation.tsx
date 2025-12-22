import { useStore } from '../stores/useStore';
import { PlusCircle, Wand2, List, Settings } from 'lucide-react';

const tabs = [
  { id: 'input' as const, label: '商品入力', icon: PlusCircle },
  { id: 'generate' as const, label: '投稿文生成', icon: Wand2 },
  { id: 'manage' as const, label: '管理', icon: List },
  { id: 'settings' as const, label: '設定', icon: Settings },
];

export default function TabNavigation() {
  const { activeTab, setActiveTab, products, posts } = useStore();

  return (
    <nav className="bg-white border-b shadow-sm">
      <div className="container mx-auto px-4 max-w-6xl">
        <div className="flex gap-1">
          {tabs.map((tab) => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.id;

            // バッジ表示
            let badge = null;
            if (tab.id === 'input' && products.length > 0) {
              badge = products.length;
            } else if (tab.id === 'manage' && posts.length > 0) {
              badge = posts.length;
            }

            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`
                  flex items-center gap-2 px-4 py-3 text-sm font-medium transition-colors relative
                  ${
                    isActive
                      ? 'text-rakuten-red border-b-2 border-rakuten-red'
                      : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                  }
                `}
              >
                <Icon className="w-4 h-4" />
                {tab.label}
                {badge !== null && (
                  <span className="ml-1 px-1.5 py-0.5 text-xs bg-gray-200 text-gray-700 rounded-full">
                    {badge}
                  </span>
                )}
              </button>
            );
          })}
        </div>
      </div>
    </nav>
  );
}
