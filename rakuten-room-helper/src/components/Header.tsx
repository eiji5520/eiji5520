import { ShoppingBag } from 'lucide-react';

export default function Header() {
  return (
    <header className="bg-rakuten-red text-white shadow-lg">
      <div className="container mx-auto px-4 py-4 max-w-6xl">
        <div className="flex items-center gap-3">
          <ShoppingBag className="w-8 h-8" />
          <div>
            <h1 className="text-xl font-bold">楽天ROOM投稿支援ツール</h1>
            <p className="text-sm text-red-100">投稿文生成・管理アプリ</p>
          </div>
        </div>
      </div>
    </header>
  );
}
