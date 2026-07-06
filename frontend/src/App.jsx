import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { useAuthStore } from './store'
import Login from './pages/Login'
import Register from './pages/Register'
import Layout from './components/Layout'
import Feed from './pages/Feed'
import Discover from './pages/Discover'
import Live from './pages/Live'
import Profile from './pages/Profile'
import Messages from './pages/Messages'
import Notifications from './pages/Notifications'
import Wallet from './pages/Wallet'
import Sounds from './pages/Sounds'
import Effects from './pages/Effects'
import MyAvatar from './pages/MyAvatar'
import SettingsPage from './pages/Settings'

function Private({ children }) {
  const token = useAuthStore(s => s.token)
  return token ? children : <Navigate to="/login" replace />
}

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login"    element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/" element={<Private><Layout /></Private>}>
          <Route index                   element={<Feed />} />
          <Route path="discover"         element={<Discover />} />
          <Route path="live"             element={<Live />} />
          <Route path="profile"          element={<Profile />} />
          <Route path="messages"         element={<Messages />} />
          <Route path="notifications"    element={<Notifications />} />
          <Route path="wallet"           element={<Wallet />} />
          <Route path="sounds"           element={<Sounds />} />
          <Route path="effects"          element={<Effects />} />
          <Route path="avatar"           element={<MyAvatar />} />
          <Route path="settings"         element={<SettingsPage />} />
        </Route>
      </Routes>
    </BrowserRouter>
  )
}
