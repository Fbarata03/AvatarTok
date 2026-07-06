import { Heart, MessageCircle, UserPlus, Gift, Trophy, Bell } from 'lucide-react'
import { Avatar } from '../components/Layout'

const TODAY = [
  { id: 1, user: 'dance_queen', action: 'curtiu seu vídeo',      time: '2 minutos atrás',  icon: <Heart size={16} fill="#ec4899" color="#ec4899" />,       iconBg: 'rgba(236,72,153,0.15)' },
  { id: 2, user: 'comedy_king', action: 'comentou: "Muito bom! 😍"', time: '15 minutos atrás', icon: <MessageCircle size={16} color="#3b82f6" />,            iconBg: 'rgba(59,130,246,0.15)' },
  { id: 3, user: 'gamer_pro',   action: 'começou a seguir você', time: '1 hora atrás',    icon: <UserPlus size={16} color="#7c3aed" />,                      iconBg: 'rgba(124,58,237,0.15)' },
  { id: 4, user: 'art_creator', action: 'enviou um presente 🎁', time: '3 horas atrás',   icon: <Gift size={16} color="#f59e0b" />,                          iconBg: 'rgba(245,158,11,0.15)' },
]
const WEEK = [
  { id: 5, user: 'music_star',  action: 'mencionou você em um comentário', time: '2 dias atrás', icon: <MessageCircle size={16} color="#06b6d4" />, iconBg: 'rgba(6,182,212,0.15)' },
  { id: 6, user: null,          action: 'Seu vídeo alcançou 10K visualizações! 🎉', time: '3 dias atrás', icon: <Trophy size={16} color="#10b981" />,  iconBg: 'rgba(16,185,129,0.15)', system: true },
]

export default function Notifications() {
  return (
    <div style={{ maxWidth: 680, margin: '0 auto', padding: '24px 24px 48px' }}>
      <h1 style={{ fontWeight: 800, fontSize: 20, marginBottom: 24, display: 'flex', alignItems: 'center', gap: 8 }}>
        🔔 Notificações
      </h1>

      <Section label="HOJE">
        {TODAY.map(n => <NotifRow key={n.id} n={n} />)}
      </Section>

      <Section label="ESTA SEMANA">
        {WEEK.map(n => <NotifRow key={n.id} n={n} />)}
      </Section>
    </div>
  )
}

function Section({ label, children }) {
  return (
    <div style={{ marginBottom: 28 }}>
      <div style={{ fontSize: 11, fontWeight: 700, letterSpacing: '1px', color: 'var(--muted)', marginBottom: 10 }}>
        {label}
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
        {children}
      </div>
    </div>
  )
}

function NotifRow({ n }) {
  return (
    <div style={{
      display: 'flex', alignItems: 'center', gap: 12,
      padding: '14px 16px', borderRadius: 'var(--radius)',
      background: 'var(--surface)', border: '1px solid var(--border)',
      cursor: 'pointer', transition: 'border-color .15s',
    }}
      onMouseEnter={e => e.currentTarget.style.borderColor = 'rgba(255,255,255,0.1)'}
      onMouseLeave={e => e.currentTarget.style.borderColor = 'var(--border)'}
    >
      {n.system ? (
        <div style={{
          width: 40, height: 40, borderRadius: '50%', flexShrink: 0,
          background: n.iconBg, display: 'flex', alignItems: 'center', justifyContent: 'center',
        }}>
          {n.icon}
        </div>
      ) : (
        <Avatar name={n.user} size={40} />
      )}

      <div style={{ flex: 1, minWidth: 0 }}>
        <p style={{ fontSize: 14, lineHeight: 1.45 }}>
          {!n.system && <strong style={{ fontWeight: 700 }}>@{n.user}</strong>}{' '}
          {n.action}
        </p>
        <div style={{ color: 'var(--muted)', fontSize: 12, marginTop: 3 }}>{n.time}</div>
      </div>

      <div style={{
        width: 34, height: 34, borderRadius: '50%', flexShrink: 0,
        background: n.iconBg, display: 'flex', alignItems: 'center', justifyContent: 'center',
      }}>
        {n.icon}
      </div>
    </div>
  )
}
