import { useState } from 'react'
import { ChevronRight, Mail, Phone, Lock, Eye, MessageCircle, MessageSquare, Bell, Smartphone, Heart, Sparkles, Smile, Wallet, CreditCard, HelpCircle, Flag, LogOut } from 'lucide-react'

const SECTIONS = [
  {
    label: 'CONTA',
    items: [
      { icon: <Mail size={16} color="#3b82f6" />, title: 'Email', sub: 'usuario@avatartok.com', badge: { label: '✓ Verificado', color: '#10b981' } },
      { icon: <Phone size={16} color="#10b981" />, title: 'Telefone', sub: '+55 11 90000-0000', badge: { label: 'Adicionar', color: '#f59e0b' } },
      { icon: <Lock size={16} color="#7c3aed" />, title: 'Senha', sub: 'Alterada há 30 dias' },
    ],
  },
  {
    label: 'PRIVACIDADE',
    items: [
      { icon: <Eye size={16} color="#6e6c85" />, title: 'Perfil privado', sub: 'Apenas seguidores podem ver seus vídeos', toggle: false },
      { icon: <MessageCircle size={16} color="#6e6c85" />, title: 'Mensagens', sub: 'Quem pode enviar mensagens' },
      { icon: <MessageSquare size={16} color="#6e6c85" />, title: 'Comentários', sub: 'Todos podem comentar' },
    ],
  },
  {
    label: 'NOTIFICAÇÕES',
    items: [
      { icon: <Smartphone size={16} color="#f59e0b" />, title: 'Push notifications', sub: 'Receber notificações no dispositivo', toggle: true },
      { icon: <Mail size={16} color="#f59e0b" />, title: 'Email notifications', sub: 'Receber atualizações por email', toggle: true },
      { icon: <Heart size={16} color="#ec4899" />, title: 'Curtidas e comentários', sub: 'Notificar sobre interações', toggle: true },
    ],
  },
  {
    label: 'AVATAR',
    items: [
      { icon: <Sparkles size={16} color="#a78bfa" />, title: 'Editar Avatar', sub: 'Personalizar seu avatar 3D' },
      { icon: <Smile size={16} color="#a78bfa" />, title: 'Expressões faciais', sub: 'Calibrar rastreamento facial' },
    ],
  },
  {
    label: 'PAGAMENTOS',
    items: [
      { icon: <Wallet size={16} color="#f59e0b" />, title: 'Carteira', sub: '28,450 🪙 disponíveis' },
      { icon: <CreditCard size={16} color="#6e6c85" />, title: 'Métodos de pagamento', sub: 'Cartão de crédito ···· 4242' },
    ],
  },
  {
    label: 'SUPORTE',
    items: [
      { icon: <HelpCircle size={16} color="#6e6c85" />, title: 'Central de ajuda', sub: 'FAQ e tutoriais' },
      { icon: <Flag size={16} color="#6e6c85" />, title: 'Reportar problema', sub: 'Enviar feedback' },
    ],
  },
]

export default function SettingsPage() {
  const [toggles, setToggles] = useState({ 0: false, 1: true, 2: true })

  return (
    <div style={{ maxWidth: 680, margin: '0 auto', padding: '24px 24px 48px' }}>
      <h1 style={{ fontWeight: 800, fontSize: 20, marginBottom: 24 }}>Configurações</h1>

      {SECTIONS.map(sec => (
        <div key={sec.label} style={{ marginBottom: 24 }}>
          <div style={{ fontSize: 11, fontWeight: 700, letterSpacing: '1px', color: 'var(--muted)', marginBottom: 8 }}>
            {sec.label}
          </div>
          <div style={{ background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 'var(--radius-lg)', overflow: 'hidden' }}>
            {sec.items.map((item, idx) => (
              <SettingRow
                key={item.title}
                item={item}
                idx={idx}
                last={idx === sec.items.length - 1}
                toggled={toggles[idx]}
                onToggle={() => setToggles(t => ({ ...t, [idx]: !t[idx] }))}
              />
            ))}
          </div>
        </div>
      ))}

      {/* Sign out */}
      <div style={{
        background: 'var(--surface)', border: '1px solid rgba(239,68,68,0.3)',
        borderRadius: 'var(--radius-lg)', overflow: 'hidden',
      }}>
        <button style={{
          width: '100%', display: 'flex', alignItems: 'center', gap: 12,
          padding: '16px 18px', background: 'none', cursor: 'pointer',
          transition: 'background .15s',
        }}
          onMouseEnter={e => e.currentTarget.style.background = 'rgba(239,68,68,0.06)'}
          onMouseLeave={e => e.currentTarget.style.background = 'none'}
        >
          <div style={{
            width: 34, height: 34, borderRadius: 10, flexShrink: 0,
            background: 'rgba(239,68,68,0.12)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
          }}>
            <LogOut size={16} color="#ef4444" />
          </div>
          <div style={{ flex: 1, textAlign: 'left' }}>
            <div style={{ fontWeight: 700, fontSize: 14, color: '#ef4444' }}>Sair da conta</div>
            <div style={{ color: 'var(--muted)', fontSize: 12, marginTop: 1 }}>Fazer logout do AvatarTok</div>
          </div>
        </button>
      </div>
    </div>
  )
}

function SettingRow({ item, idx, last, toggled, onToggle }) {
  return (
    <div style={{
      display: 'flex', alignItems: 'center', gap: 12,
      padding: '14px 18px',
      borderBottom: last ? 'none' : '1px solid var(--border)',
      cursor: 'pointer', transition: 'background .12s',
    }}
      onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
      onMouseLeave={e => e.currentTarget.style.background = 'none'}
    >
      <div style={{
        width: 34, height: 34, borderRadius: 10, flexShrink: 0,
        background: 'var(--surface2)',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
      }}>
        {item.icon}
      </div>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontWeight: 600, fontSize: 14 }}>{item.title}</div>
        <div style={{ color: 'var(--muted)', fontSize: 12, marginTop: 1 }}>{item.sub}</div>
      </div>
      {item.badge && (
        <span style={{
          padding: '3px 10px', borderRadius: 20, fontSize: 11, fontWeight: 700,
          background: item.badge.color + '20', color: item.badge.color,
          border: `1px solid ${item.badge.color}40`,
        }}>{item.badge.label}</span>
      )}
      {item.toggle !== undefined && (
        <Toggle on={toggled} onClick={onToggle} />
      )}
      {item.toggle === undefined && !item.badge && (
        <ChevronRight size={16} color="var(--muted)" />
      )}
    </div>
  )
}

function Toggle({ on, onClick }) {
  return (
    <button onClick={onClick} style={{
      width: 44, height: 24, borderRadius: 12, flexShrink: 0,
      background: on ? 'var(--primary)' : 'var(--surface3)',
      border: on ? 'none' : '1px solid var(--border)',
      position: 'relative', cursor: 'pointer',
      transition: 'background .2s',
      boxShadow: on ? '0 0 10px var(--primary-glow)' : 'none',
    }}>
      <div style={{
        position: 'absolute', top: 2, left: on ? 22 : 2,
        width: 20, height: 20, borderRadius: '50%', background: '#fff',
        transition: 'left .2s',
        boxShadow: '0 1px 4px rgba(0,0,0,0.3)',
      }} />
    </button>
  )
}
