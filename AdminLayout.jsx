import React, { useState } from 'react';

export default function AdminLayout({ children }) {
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const [active, setActive] = useState(true);

  const menus = ['Dashboard', 'Accounts', 'Analytics', 'Scheduler', 'Settings'];

  return (
    <div className="min-h-screen flex">
      <aside
        className={`${
          sidebarOpen ? 'w-64' : 'w-16'
        } bg-gray-800 text-gray-100 transition-all duration-300`}
      >
        <div className="flex items-center justify-between p-4">
          {sidebarOpen && <span className="font-bold">Menu</span>}
          <button onClick={() => setSidebarOpen(!sidebarOpen)} className="focus:outline-none">
            {sidebarOpen ? '<' : '>'}
          </button>
        </div>
        <nav className="mt-4">
          <ul>
            {menus.map((m) => (
              <li key={m} className="p-2 hover:bg-gray-700">
                {sidebarOpen ? m : m.charAt(0)}
              </li>
            ))}
          </ul>
        </nav>
      </aside>
      <div className="flex-1 flex flex-col">
        <header className="bg-gray-100 p-4 flex justify-end">
          <label className="flex items-center cursor-pointer">
            <span className="mr-2">稼働ON</span>
            <input
              type="checkbox"
              className="form-checkbox"
              checked={active}
              onChange={() => setActive(!active)}
            />
          </label>
        </header>
        <main className="p-4 bg-white flex-1">{children}</main>
      </div>
    </div>
  );
}

