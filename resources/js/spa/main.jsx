import React from 'react';
import { createRoot } from 'react-dom/client';

function App() {
    return <div>SPA placeholder</div>;
}

const el = document.getElementById('spa-root');
if (el) {
    createRoot(el).render(<App />);
}
