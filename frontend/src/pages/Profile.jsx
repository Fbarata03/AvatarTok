import { useState } from 'react'
import { Edit3, Share2, Play, CheckCircle } from 'lucide-react'
import { useAuthStore } from '../store'
import { Avatar } from '../components/Layout'

const STATS = [
  { label: 'Vídeos',         value: '142' },
  { label: 'Seguidores',     value: '45.2K' },
  { label: 'Seguido',        value: '892' },
  { label: 'Visualizações',  value: '2.4M' },
]
const TABS = ['Vídeos', 'Curtidos', 'Salvos']
const MOCK_VIDEOS = [
  { grad: 'linear-gradient(160deg,#1a0535,#0d1a40)', emoji: '🎭', views: '2.4M' },
  { grad: 'linear-gradient(160deg,#200a0a,#0a1020)', emoji: '😂', views: '890K' },
  { grad: 'linear-gradient(160deg,#0a1020,#1a0535)', emoji: '⚔️', views: '1.2M' },
  { grad: 'linear-gradient(160deg,#001820,#0d0540)', emoji: '🌟', views: '567K' },
  { grad: 'linear-gradient(160deg,#0d0a20,#200030)', emoji: '💃', views: '3.1M' },
  { grad: 'linear-gradient(160deg,#200010,#0a0a30)', emoji: '🎵', views: '1.8M' },
]

export default function Profile() {
  const { user } = useAuthStore()
  const [tab, setTab] = useState(0)

  return (
    <div style={{ maxWidth: '100%', overflowX: 'hidden' }}>
      {/* Banner */}
      <div style={{
        height: 180,
        background: 'linear-gradient(135deg, #7c3aed 0%, #a855f7 40%, #ec4899 100%)',
        position: 'relative', flexShrink: 0,
      }}>
        <button style={{
          position: 'absolute', top: 14, right: 14,
          background: 'rgba(0,0,0,0.3)', backdropFilter: 'blur(8px)',
          border: '1px solid rgba(255,255,255,0.2)', borderRadius: 8,
          padding: '6px 12px', color: '#fff', fontSize: 12, fontWeight: 600,
        }}>
          Editar capa
        </button>
      </div>

      {/* Profile header */}
      <div style={{ textAlign: 'center', padding: '0 24px 0', position: 'relative' }}>
        {/* Avatar overlapping banner */}
        <div style={{ display: 'flex', justifyContent: 'center' }}>
          <div style={{ position: 'relative', marginTop: -40 }}>
            <div style={{
              width: 80, height: 80, borderRadius: '50%',
              background: 'linear-gradient(135deg,#7c3aed,#ec4899)',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontSize: 32, fontWeight: 800, color: '#fff',
              border: '3px solid var(--bg)',
              boxShadow: '0 0 24px var(--primary-glow)',
            }}>
              {(user?.username?.[0] || 'A').toUpperCase()}
            </div>
          </div>
        </div>

        <div style={{ marginTop: 10, marginBottom: 16 }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6, marginBottom: 4 }}>
            <h2 style={{ fontWeight: 800, fontSize: 18 }}>{user?.username || 'Avatar User'}</h2>
            <CheckCircle size={16} color="#a78bfa" fill="#a78bfa" />
          </div>
          <div style={{ color: 'var(--muted)', fontSize: 13, marginBottom: 6 }}>@{user?.username || 'avatar_user'}</div>
          <p style={{ color: 'var(--text-2)', fontSize: 13, lineHeight: 1.5, maxWidth: 400, margin: '0 auto 16px' }}>
            Criador de conteúdo com avatar 3D 🎭✨<br />Dança • Comédia • Arte
          </p>

          {/* Stats */}
          <div style={{ display: 'flex', justifyContent: 'center', gap: 32, marginBottom: 18 }}>
            {STATS.map(s => (
              <div key={s.label} style={{ textAlign: 'center' }}>
                <div style={{ fontWeight: 800, fontSize: 18, letterSpacing: '-0.5px' }}>{s.value}</div>
                <div style={{ color: 'var(--muted)', fontSize: 11, marginTop: 2 }}>{s.label}</div>
              </div>
            ))}
          </div>

          {/* Actions */}
          <div style={{ display: 'flex', justifyContent: 'center', gap: 10 }}>
            <button style={{
              display: 'flex', alignItems: 'center', gap: 6,
              padding: '9px 22px', borderRadius: 20,
              background: 'var(--grad)', color: '#fff', fontWeight: 700, fontSize: 13,
              boxShadow: '0 3px 14px var(--primary-glow)',
            }}>
              <Edit3 size={14} /> Editar Perfil
            </button>
            <button style={{
              display: 'flex', alignItems: 'center', gap: 6,
              padding: '9px 20px', borderRadius: 20,
              background: 'var(--surface)', border: '1px solid var(--border)',
              color: 'var(--text-2)', fontWeight: 600, fontSize: 13,
            }}>
              <Share2 size={14} /> Compartilhar
            </button>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div style={{ display: 'flex', borderBottom: '1px solid var(--border)', borderTop: '1px solid var(--border)' }}>
        {TABS.map((t, i) => (
          <button key={t} onClick={() => setTab(i)} style={{
            flex: 1, padding: '14px 0', background: 'none', fontSize: 14,
            color: tab === i ? 'var(--text)' : 'var(--muted)',
            fontWeight: tab === i ? 700 : 400,
            borderBottom: tab === i ? '2px solid #a78bfa' : '2px solid transparent',
            transition: 'all .15s',
          }}>{t}</button>
        ))}
      </div>

      {/* Videos grid */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 2, padding: 2 }}>
        {MOCK_VIDEOS.map((v, i) => (
          <div key={i} style={{
            background: v.grad, aspectRatio: '9/16', position: 'relative',
            cursor: 'pointer', overflow: 'hidden',
            transition: 'opacity .15s',
          }}
            onMouseEnter={e => e.currentTarget.style.opacity = '0.85'}
            onMouseLeave={e => e.currentTarget.style.opacity = '1'}
          >
            <div style={{
              position: 'absolute', inset: 0,
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontSize: 36,
            }}>{v.emoji}</div>
            <div style={{
              position: 'absolute', bottom: 0, left: 0, right: 0,
              padding: '20px 8px 8px',
              background: 'linear-gradient(transparent, rgba(0,0,0,0.65))',
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 4, color: '#fff', fontSize: 12, fontWeight: 700 }}>
                <Play size={10} fill="#fff" /> {v.views}
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
