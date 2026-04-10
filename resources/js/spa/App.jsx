import { Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout.jsx';
import ProtectedRoute from './components/ProtectedRoute.jsx';
import CanvasPage from './pages/CanvasPage.jsx';
import WorkspacePage from './pages/WorkspacePage.jsx';
import BrowsePage from './pages/BrowsePage.jsx';
import SettingsPage from './pages/SettingsPage.jsx';
import LoginPage from './pages/LoginPage.jsx';

export default function App() {
    return (
        <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/*" element={
                <ProtectedRoute>
                    <Layout>
                        <Routes>
                            <Route path="/canvas" element={<CanvasPage />} />
                            <Route path="/workspace/:username/:slug" element={<WorkspacePage />} />
                            <Route path="/browse" element={<BrowsePage />} />
                            <Route path="/settings" element={<SettingsPage />} />
                            <Route path="*" element={<Navigate to="/canvas" replace />} />
                        </Routes>
                    </Layout>
                </ProtectedRoute>
            } />
        </Routes>
    );
}
