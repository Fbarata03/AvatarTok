import { Outlet, NavLink, useNavigate, useLocation } from 'react-router-dom'
import { useAuthStore } from '../store'
import {
  Home, Compass, Radio, Music2, Sparkles,
  MessageCircle, Bell, Wallet, User, Star, Settings,
  LogOut, Search,
} from 'lucide-react'

const NAV_MAIN = [
  { to: '/',          icon: Home,          label: 'Para Você',    end: true },
  { to: '/discover',  icon: Compass,       label: 'Descobrir' },
  { to: '/live',      icon: Radio,         label: 'Ao Vivo',      live: true },
  { to: '/sounds',    icon: Music2,        label: 'Sons' },
  { to: '/effects',   icon: Sparkles,      label: 'Efeitos' },
]
const NAV_SOCIAL = [
  { to: '/messages',      icon: MessageCircle, label: 'Mensagens',    badge: 3  },
  { to: '/notifications', icon: Bell,          label: 'Notificações', badge: 12 },
  { to: '/wallet',        icon: Wallet,        label: 'Carteira' },
]
const NAV_CONTA = [
  { to: '/profile',  icon: User,     label: 'Perfil' },
  { to: '/avatar',   icon: Star,     label: 'Meu Avatar' },
  { to: '/settings', icon: Settings, label: 'Configurações' },
]
const TITLES = {
  '/': 'Para Você', '/discover': 'Descobrir', '/live': 'Ao Vivo',
  '/sounds': 'Sons', '/effects': 'Efeitos & Filtros', '/messages': 'Mensagens',
  '/notifications': 'Notificações', '/wallet': 'Carteira',
  '/profile': 'Perfil', '/avatar': 'Meu Avatar', '/settings': 'Configurações',
}

export default function Layout() {
  const { user, logout } = useAuthStore()
  const navigate = useNavigate()
  const { pathname } = useLocation()

  return (
    <div style={{ display: 'flex', height: '100vh', overflow: 'hidden' }}>
      {/* ── Sidebar ─────────────────────────────── */}
      <nav style={{
        width: 168, flexShrink: 0,
        background: 'var(--surface)',
        borderRight: '1px solid var(--border)',
        display: 'flex', flexDirection: 'column',
      }}>
        {/* Logo */}
        <div style={{ padding: '18px 14px 14px', display: 'flex', alignItems: 'center', gap: 9, flexShrink: 0 }}>
          <div style={{
            width: 32, height: 32, borderRadius: 9,
            background: 'var(--grad)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            boxShadow: '0 0 16px var(--primary-glow)', flexShrink: 0,
          }}>
            <span style={{ fontSize: 14, fontWeight: 900, color: '#fff' }}>A</span>
          </div>
          <span style={{ fontWeight: 800, fontSize: 15, letterSpacing: '-0.5px' }}>AvatarTok</span>
        </div>

        {/* Nav */}
        <div style={{ flex: 1, overflowY: 'auto', padding: '0 8px' }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
            {NAV_MAIN.map(p => <NavItem key={p.to} {...p} />)}
          </div>
          <SectionLabel>SOCIAL</SectionLabel>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
            {NAV_SOCIAL.map(p => <NavItem key={p.to} {...p} />)}
          </div>
          <SectionLabel>CONTA</SectionLabel>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
            {NAV_CONTA.map(p => <NavItem key={p.to} {...p} />)}
          </div>
        </div>

        {/* User row */}
        <div style={{ padding: '10px', borderTop: '1px solid var(--border)', flexShrink: 0 }}>
          <div style={{
            display: 'flex', alignItems: 'center', gap: 8,
            padding: '8px 6px', borderRadius: 10, background: 'var(--surface2)',
          }}>
            <Avatar name={user?.username} size={28} />
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: 12, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                {user?.username || 'Avatar User'}
              </div>
              <div style={{ fontSize: 11, color: 'var(--muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                @{user?.username || 'avatar_user'}
              </div>
            </div>
            <button
              onClick={() => { logout(); navigate('/login') }}
              style={{ background: 'none', color: 'var(--muted)', padding: 4, borderRadius: 6, display: 'flex', flexShrink: 0, transition: 'color .15s' }}
              onMouseEnter={e => e.currentTarget.style.color = 'var(--danger)'}
              onMouseLeave={e => e.currentTarget.style.color = 'var(--muted)'}
            >
              <LogOut size={13} />
            </button>
          </div>
        </div>
      </nav>

      {/* ── Main ─────────────────────────────────── */}
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        {/* Header */}
        <header style={{
          height: 58, flexShrink: 0,
          borderBottom: '1px solid var(--border)',
          display: 'flex', alignItems: 'center',
          padding: '0 24px', gap: 16,
          background: 'var(--bg)',
        }}>
          <span style={{ fontWeight: 700, fontSize: 15, flex: 1 }}>
            {TITLES[pathname] || 'AvatarTok'}
          </span>
          <div style={{
            display: 'flex', alignItems: 'center', gap: 8,
            background: 'var(--surface)', border: '1px solid var(--border)',
            borderRadius: 24, padding: '0 14px', width: 280,
          }}>
            <Search size={14} color="var(--muted)" />
            <input
              placeholder="Buscar criadores, sons, hashtags..."
              style={{ flex: 1, background: 'none', border: 'none', color: 'var(--text)', fontSize: 13, padding: '9px 0', outline: 'none' }}
            />
          </div>
          <HIcon><Bell size={17} /></HIcon>
          <HIcon><MessageCircle size={17} /></HIcon>
        </header>

        {/* Content */}
        <main style={{ flex: 1, overflowY: 'auto', background: 'var(--bg)' }}>
          <Outlet />
        </main>
      </div>
    </div>
  )
}

function SectionLabel({ children }) {
  return (
    <div style={{ fontSize: 10, fontWeight: 700, letterSpacing: '1px', color: 'var(--muted)', padding: '14px 10px 4px' }}>
      {children}
    </div>
  )
}

function NavItem({ to, icon: Icon, label, end, live, badge }) {
  return (
    <NavLink to={to} end={end} style={({ isActive }) => ({
      display: 'flex', alignItems: 'center', gap: 9,
      padding: '8px 10px', borderRadius: 8,
      fontSize: 13, fontWeight: isActive ? 600 : 400,
      color: isActive ? 'var(--text)' : 'var(--muted)',
      background: isActive ? 'rgba(124,58,237,0.12)' : 'transparent',
      transition: 'all .12s',
    })}
      onMouseEnter={e => { if (!e.currentTarget.dataset.active) e.currentTarget.style.background = 'rgba(255,255,255,0.04)' }}
      onMouseLeave={e => { if (!e.currentTarget.dataset.active) e.currentTarget.style.background = '' }}
    >
      {({ isActive }) => (
        <>
          <Icon size={16} color={isActive ? '#a78bfa' : 'var(--muted)'} />
          <span style={{ flex: 1 }}>{label}</span>
          {live && (
            <span style={{ background: '#ef4444', color: '#fff', fontSize: 9, fontWeight: 800, padding: '2px 5px', borderRadius: 4, letterSpacing: '0.3px' }}>
              LIVE
            </span>
          )}
          {badge > 0 && (
            <span style={{
              background: '#ef4444', color: '#fff', fontSize: 10, fontWeight: 700,
              minWidth: 17, height: 17, borderRadius: 9,
              display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '0 4px',
            }}>{badge}</span>
          )}
        </>
      )}
    </NavLink>
  )
}

function HIcon({ children }) {
  return (
    <button style={{
      width: 36, height: 36, borderRadius: 10,
      background: 'var(--surface)', border: '1px solid var(--border)',
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      color: 'var(--muted)', transition: 'all .12s', flexShrink: 0,
    }}
      onMouseEnter={e => { e.currentTarget.style.color = 'var(--text)'; e.currentTarget.style.borderColor = 'rgba(255,255,255,0.12)' }}
      onMouseLeave={e => { e.currentTarget.style.color = 'var(--muted)'; e.currentTarget.style.borderColor = 'var(--border)' }}
    >
      {children}
    </button>
  )
}

export function Avatar({ name = '?', size = 40, src, ring }) {
  const GRADS = [
    'linear-gradient(135deg,#7c3aed,#ec4899)',
    'linear-gradient(135deg,#3b82f6,#06b6d4)',
    'linear-gradient(135deg,#10b981,#3b82f6)',
    'linear-gradient(135deg,#f59e0b,#ef4444)',
    'linear-gradient(135deg,#ec4899,#f59e0b)',
  ]
  const grad = GRADS[(name?.charCodeAt(0) || 0) % GRADS.length]
  if (src) return (
    <img src={src} width={size} height={size}
      style={{ borderRadius: '50%', objectFit: 'cover', flexShrink: 0,
        boxShadow: ring ? `0 0 0 2px var(--bg), 0 0 0 3px var(--primary)` : 'none' }}
      alt="" />
  )
  return (
    <div style={{
      width: size, height: size, borderRadius: '50%', flexShrink: 0,
      background: grad, display: 'flex', alignItems: 'center', justifyContent: 'center',
      fontWeight: 800, fontSize: size * 0.37, color: '#fff',
      boxShadow: ring ? `0 0 0 2px var(--bg), 0 0 0 3px var(--primary)` : 'none',
    }}>
      {(name?.[0] || '?').toUpperCase()}
    </div>
  )
}

export function Btn({ children, variant = 'primary', onClick, style: sx, disabled }) {
  const base = {
    display: 'inline-flex', alignItems: 'center', gap: 6,
    borderRadius: 20, fontWeight: 700, padding: '9px 20px', fontSize: 13,
    opacity: disabled ? 0.5 : 1, cursor: disabled ? 'not-allowed' : 'pointer', transition: 'all .15s',
  }
  const V = {
    primary: { background: 'var(--grad)', color: '#fff', boxShadow: '0 3px 12px var(--primary-glow)' },
    ghost:   { background: 'var(--surface2)', color: 'var(--text-2)', border: '1px solid var(--border)' },
    danger:  { background: 'rgba(239,68,68,0.12)', color: 'var(--danger)', border: '1px solid rgba(239,68,68,0.25)' },
    outline: { background: 'transparent', color: 'var(--text)', border: '1px solid var(--border)' },
  }
  return <button onClick={disabled ? undefined : onClick} style={{ ...base, ...V[variant], ...(sx || {}) }}>{children}</button>
}
