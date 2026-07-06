import { useState } from 'react'
import { Radio, Users } from 'lucide-react'
import { Avatar } from '../components/Layout'

const STREAMS = [
  { id: 1, title: 'Dançando com meu a...', username: 'dance_queen', viewers: 12400, emoji: '💃', grad: 'linear-gradient(160deg,#4a0080,#1a0540,#0d0030)' },
  { id: 2, title: 'Comédia ao vivo 😂',   username: 'comedy_king',  viewers: 8900,  emoji: '😂', grad: 'linear-gradient(160deg,#1a0030,#0d1040,#080020)' },
  { id: 3, title: 'Gameplay épica 🎮',     username: 'gamer_pro',    viewers: 24100, emoji: '🎮', grad: 'linear-gradient(160deg,#001840,#0d0540,#001020)' },
  { id: 4, title: 'Criando arte digital 🎨', username: 'art_creator', viewers: 5600, emoji: '🎨', grad: 'linear-gradient(160deg,#200040,#0a0050,#100020)' },
  { id: 5, title: 'Música ao vivo 🎵',    username: 'music_star',   viewers: 3200,  emoji: '🎵', grad: 'linear-gradient(160deg,#001a30,#0d0a40,#000820)' },
  { id: 6, title: 'Q&A com seguidores ...', username: 'tech_avatar',  viewers: 18700, emoji: '💬', grad: 'linear-gradient(160deg,#0a0a40,#20003a,#050030)' },
]

export default function Live() {
  const [filter, setFilter] = useState('all')
  const filters = [
    { id: 'all', label: 'Todos' },
    { id: 'gaming', label: '🎮 Gaming' },
    { id: 'music', label: '🎵 Música' },
    { id: 'talk', label: '💬 Bate-papo' },
    { id: 'dance', label: '💃 Dança' },
  ]

  return (
    <div style={{ padding: '24px 24px 48px' }}>
      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 24 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <div style={{ width: 10, height: 10, borderRadius: '50%', background: '#ef4444', boxShadow: '0 0 10px #ef4444', animation: 'pulse-dot 1.5s infinite' }} />
          <h1 style={{ fontWeight: 800, fontSize: 18 }}>Lives ao vivo agora</h1>
        </div>
        <button style={{
          display: 'flex', alignItems: 'center', gap: 8,
          padding: '10px 20px', borderRadius: 24,
          background: '#ef4444', color: '#fff', fontWeight: 700, fontSize: 13,
          boxShadow: '0 3px 16px rgba(239,68,68,0.35)',
          transition: 'all .15s',
        }}
          onMouseEnter={e => e.currentTarget.style.boxShadow = '0 4px 24px rgba(239,68,68,0.55)'}
          onMouseLeave={e => e.currentTarget.style.boxShadow = '0 3px 16px rgba(239,68,68,0.35)'}
        >
          <Radio size={15} /> Iniciar Live
        </button>
      </div>

      {/* Grid */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 12 }}>
        {STREAMS.map(s => <StreamCard key={s.id} stream={s} />)}
      </div>
    </div>
  )
}

function StreamCard({ stream }) {
  const [hovered, setHovered] = useState(false)
  return (
    <div style={{
      borderRadius: 'var(--radius-lg)', overflow: 'hidden', cursor: 'pointer',
      position: 'relative', aspectRatio: '9/14',
      background: stream.grad,
      transform: hovered ? 'translateY(-3px)' : 'none',
      boxShadow: hovered ? '0 12px 40px rgba(0,0,0,0.5)' : '0 4px 16px rgba(0,0,0,0.3)',
      transition: 'all .2s',
    }}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
    >
      {/* Glow radial */}
      <div style={{
        position: 'absolute', inset: 0, opacity: hovered ? 0.6 : 0.35,
        background: 'radial-gradient(circle at 50% 40%, rgba(124,58,237,0.5), transparent 65%)',
        transition: 'opacity .2s',
      }} />

      {/* Avatar emoji center */}
      <div style={{
        position: 'absolute', inset: 0,
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        fontSize: 52,
      }}>{stream.emoji}</div>

      {/* AO VIVO badge */}
      <div style={{
        position: 'absolute', top: 10, left: 10,
        background: '#ef4444', color: '#fff',
        fontSize: 10, fontWeight: 800, padding: '3px 8px', borderRadius: 20,
        display: 'flex', alignItems: 'center', gap: 4, letterSpacing: '0.5px',
      }}>
        <span style={{ width: 5, height: 5, borderRadius: '50%', background: '#fff', animation: 'pulse-dot 1.5s infinite' }} />
        AO VIVO
      </div>

      {/* Viewers */}
      <div style={{
        position: 'absolute', top: 10, right: 10,
        background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(8px)',
        color: '#fff', fontSize: 11, fontWeight: 600,
        padding: '3px 8px', borderRadius: 20,
        display: 'flex', alignItems: 'center', gap: 4,
      }}>
        <Users size={11} /> {fmt(stream.viewers)}
      </div>

      {/* Bottom info */}
      <div style={{
        position: 'absolute', bottom: 0, left: 0, right: 0,
        padding: '32px 10px 12px',
        background: 'linear-gradient(transparent, rgba(0,0,0,0.8))',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 7, marginBottom: 4 }}>
          <Avatar name={stream.username} size={24} />
          <span style={{ color: '#fff', fontSize: 12, fontWeight: 700 }}>@{stream.username}</span>
        </div>
        <p style={{ color: 'rgba(255,255,255,0.8)', fontSize: 12, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
          {stream.title}
        </p>
      </div>
    </div>
  )
}

function fmt(n) {
  if (n >= 1e6) return (n / 1e6).toFixed(1) + 'M'
  if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K'
  return String(n)
}
