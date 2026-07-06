import { Gift, CreditCard, TrendingUp, ArrowUpRight, ArrowDownRight, Plus } from 'lucide-react'

const TRANSACTIONS = [
  { id: 1, icon: '🎁',  label: 'Presente recebido de @dance_queen',      date: 'Hoje, 14:30',  amount: '+500',   color: '#10b981' },
  { id: 2, icon: '🎬',  label: 'Receita de vídeo (10K views)',            date: 'Ontem, 09:15', amount: '+1,200', color: '#10b981' },
  { id: 3, icon: '🎁',  label: 'Presente enviado para @comedy_king',      date: '2 dias atrás', amount: '-200',   color: '#ef4444' },
  { id: 4, icon: '💳',  label: 'Recarga de moedas',                       date: '3 dias atrás', amount: '+10,000',color: '#10b981' },
]
const GIFTS = [
  { emoji: '🌹', name: 'Rosa',      coins: 10 },
  { emoji: '🎂', name: 'Bolo',      coins: 50 },
  { emoji: '🚀', name: 'Foguete',   coins: 200 },
  { emoji: '👑', name: 'Coroa',     coins: 500 },
  { emoji: '💎', name: 'Diamante',  coins: 1000 },
  { emoji: '🦄', name: 'Unicórnio', coins: 2000 },
]

export default function Wallet() {
  return (
    <div style={{ maxWidth: 760, margin: '0 auto', padding: '24px 24px 48px' }}>
      {/* Balance card */}
      <div style={{
        background: 'linear-gradient(135deg, #7c3aed 0%, #a855f7 50%, #ec4899 100%)',
        borderRadius: 'var(--radius-xl)', padding: '28px 28px', marginBottom: 16,
        boxShadow: '0 8px 40px rgba(124,58,237,0.35)',
      }}>
        <div style={{ color: 'rgba(255,255,255,0.75)', fontSize: 13, fontWeight: 500, marginBottom: 8 }}>
          Saldo disponível
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 12, marginBottom: 8 }}>
          <div style={{ fontSize: 42, fontWeight: 900, color: '#fff', letterSpacing: '-1px', lineHeight: 1 }}>
            🪙 28,450
          </div>
        </div>
        <div style={{ color: 'rgba(255,255,255,0.7)', fontSize: 14, marginBottom: 4 }}>≈ $200.35 USD</div>
        <div style={{ color: 'rgba(255,255,255,0.55)', fontSize: 12 }}>Saldo pendente: 1,200 (em processamento)</div>
      </div>

      {/* Action buttons */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 24 }}>
        <button style={{
          padding: '18px', borderRadius: 'var(--radius-lg)',
          background: 'var(--surface)', border: '1px solid var(--border)',
          display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8,
          cursor: 'pointer', transition: 'border-color .15s',
        }}
          onMouseEnter={e => e.currentTarget.style.borderColor = 'rgba(124,58,237,0.4)'}
          onMouseLeave={e => e.currentTarget.style.borderColor = 'var(--border)'}
        >
          <div style={{ width: 40, height: 40, borderRadius: 12, background: 'var(--surface2)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <Plus size={20} color="var(--text-2)" />
          </div>
          <span style={{ fontWeight: 600, fontSize: 14 }}>Recarregar Moedas</span>
        </button>
        <button style={{
          padding: '18px', borderRadius: 'var(--radius-lg)',
          background: 'var(--surface)', border: '1px solid var(--border)',
          display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8,
          cursor: 'pointer', transition: 'border-color .15s',
        }}
          onMouseEnter={e => e.currentTarget.style.borderColor = 'rgba(16,185,129,0.4)'}
          onMouseLeave={e => e.currentTarget.style.borderColor = 'var(--border)'}
        >
          <div style={{ width: 40, height: 40, borderRadius: 12, background: 'rgba(16,185,129,0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <TrendingUp size={20} color="#10b981" />
          </div>
          <span style={{ fontWeight: 600, fontSize: 14 }}>Sacar Ganhos 🌿</span>
        </button>
      </div>

      {/* Transactions */}
      <h2 style={{ fontWeight: 800, fontSize: 16, marginBottom: 14, display: 'flex', alignItems: 'center', gap: 6 }}>
        🏦 Histórico de transações
      </h2>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 4, marginBottom: 32 }}>
        {TRANSACTIONS.map(t => (
          <div key={t.id} style={{
            display: 'flex', alignItems: 'center', gap: 14,
            padding: '14px 16px', borderRadius: 'var(--radius)',
            background: 'var(--surface)', border: '1px solid var(--border)',
            transition: 'border-color .15s',
          }}
            onMouseEnter={e => e.currentTarget.style.borderColor = 'rgba(255,255,255,0.1)'}
            onMouseLeave={e => e.currentTarget.style.borderColor = 'var(--border)'}
          >
            <div style={{
              width: 40, height: 40, borderRadius: 12, flexShrink: 0,
              background: 'var(--surface2)', display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontSize: 18,
            }}>{t.icon}</div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontWeight: 600, fontSize: 13, marginBottom: 3 }}>{t.label}</div>
              <div style={{ color: 'var(--muted)', fontSize: 12 }}>{t.date}</div>
            </div>
            <div style={{
              fontWeight: 800, fontSize: 15, color: t.color, flexShrink: 0,
              display: 'flex', alignItems: 'center', gap: 4,
            }}>
              {t.amount} 🪙
            </div>
          </div>
        ))}
      </div>

      {/* Gift catalog */}
      <h2 style={{ fontWeight: 800, fontSize: 16, marginBottom: 14, display: 'flex', alignItems: 'center', gap: 6 }}>
        🎁 Catálogo de presentes
      </h2>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(6, 1fr)', gap: 10, marginBottom: 16 }}>
        {GIFTS.map(g => (
          <button key={g.name} style={{
            padding: '16px 8px', borderRadius: 'var(--radius)',
            background: 'var(--surface)', border: '1px solid var(--border)',
            display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6,
            cursor: 'pointer', transition: 'all .15s',
          }}
            onMouseEnter={e => { e.currentTarget.style.borderColor = 'rgba(124,58,237,0.4)'; e.currentTarget.style.transform = 'translateY(-2px)' }}
            onMouseLeave={e => { e.currentTarget.style.borderColor = 'var(--border)'; e.currentTarget.style.transform = 'none' }}
          >
            <span style={{ fontSize: 26 }}>{g.emoji}</span>
            <span style={{ fontWeight: 700, fontSize: 12 }}>{g.name}</span>
            <span style={{ fontSize: 11, color: 'var(--muted)', display: 'flex', alignItems: 'center', gap: 2 }}>
              🪙 {g.coins}
            </span>
          </button>
        ))}
      </div>
      <div style={{ color: 'var(--muted)', fontSize: 12 }}>
        💡 Taxa de conversão: 142 moedas = $1 USD
      </div>
    </div>
  )
}
