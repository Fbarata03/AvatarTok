import { useState, useEffect } from 'react'
import { Gift, CreditCard, TrendingUp, ArrowUpRight, ArrowDownRight, Plus, X } from 'lucide-react'
import api from '../api'

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
  const [balance, setBalance] = useState(0)
  const [pending, setPending] = useState(0)
  const [history, setHistory] = useState([])
  const [loading, setLoading] = useState(true)
  const [modalType, setModalType] = useState(null) // 'topup' | 'withdraw'
  const [coinsInput, setCoinsInput] = useState('')
  const [withdrawMethod, setWithdrawMethod] = useState('paypal')
  const [withdrawAccount, setWithdrawAccount] = useState('')

  useEffect(() => {
    loadWalletData()
  }, [])

  async function loadWalletData() {
    try {
      setLoading(true)
      const res = await api.get('/monetization/wallet')
      setBalance(res.data.data?.spendable_coins || 0)
      setPending(res.data.data?.pending_coins || 0)

      // Try fetching actual transactions
      try {
        const giftRes = await api.get('/monetization/gifts/received')
        const items = giftRes.data.data?.items || []
        if (items.length > 0) {
          setHistory(items.map(item => ({
            id: item.id,
            icon: '🎁',
            label: `Presente recebido de @${item.sender_username}`,
            date: new Date(item.created_at).toLocaleDateString('pt-BR'),
            amount: `+${item.coins_total}`,
            color: '#10b981'
          })))
        } else {
          setHistory(TRANSACTIONS)
        }
      } catch {
        setHistory(TRANSACTIONS)
      }
    } catch (e) {
      console.error(e)
    } finally {
      setLoading(false)
    }
  }

  async function handleTopUp(e) {
    e.preventDefault()
    if (!coinsInput || Number(coinsInput) <= 0) return
    try {
      const { data } = await api.post('/monetization/wallet/top-up', { coins: Number(coinsInput) })
      setBalance(data.data.spendable_coins)
      setPending(data.data.pending_coins)
      setModalType(null)
      setCoinsInput('')
      alert('Recarga realizada com sucesso! Moedas adicionadas à sua carteira.')
    } catch (err) {
      alert('Falha ao processar recarga.')
    }
  }

  async function handleWithdraw(e) {
    e.preventDefault()
    if (!coinsInput || Number(coinsInput) <= 0 || !withdrawAccount) return
    try {
      await api.post('/monetization/wallet/withdraw', {
        amount_coins: Number(coinsInput),
        method: withdrawMethod,
        account_id: withdrawAccount
      })
      alert('Pedido de saque enviado com sucesso!')
      loadWalletData()
      setModalType(null)
      setCoinsInput('')
      setWithdrawAccount('')
    } catch (err) {
      alert(err.response?.data?.error || 'Saldo insuficiente ou falha ao processar saque.')
    }
  }

  if (loading) {
    return (
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '60vh', color: 'var(--muted)' }}>
        Carregando carteira...
      </div>
    )
  }

  return (
    <div style={{ maxWidth: 760, margin: '0 auto', padding: '24px 24px 48px' }}>
      {/* Balance card */}
      <div style={{
        background: 'linear-gradient(135deg, #7c3aed 0%, #a855f7 40%, #ec4899 100%)',
        borderRadius: 'var(--radius-xl)', padding: '32px', marginBottom: 20,
        boxShadow: '0 12px 40px rgba(124,58,237,0.4), inset 0 1px 1px rgba(255,255,255,0.2)',
        position: 'relative', overflow: 'hidden',
      }} className="animate-fade-in-up">
        <div style={{
          position: 'absolute', top: '-20%', right: '-10%', width: 180, height: 180,
          borderRadius: '50%', background: 'rgba(255,255,255,0.08)', filter: 'blur(30px)',
        }} />
        <div style={{
          position: 'absolute', bottom: '-40%', left: '-5%', width: 140, height: 140,
          borderRadius: '50%', background: 'rgba(236,72,153,0.15)', filter: 'blur(20px)',
        }} />

        <div style={{ color: 'rgba(255,255,255,0.8)', fontSize: 13, fontWeight: 600, marginBottom: 8, textTransform: 'uppercase', letterSpacing: '0.5px', position: 'relative', zIndex: 1 }}>
          Saldo disponível
        </div>
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 12, marginBottom: 8, position: 'relative', zIndex: 1 }}>
          <div style={{ fontSize: 44, fontWeight: 900, color: '#fff', letterSpacing: '-1px', lineHeight: 1 }}>
            🪙 {balance.toLocaleString()}
          </div>
        </div>
        <div style={{ color: 'rgba(255,255,255,0.85)', fontSize: 15, fontWeight: 600, marginBottom: 12, position: 'relative', zIndex: 1 }}>
          ≈ ${(balance / 142).toFixed(2)} USD
        </div>
        <div style={{ color: 'rgba(255,255,255,0.6)', fontSize: 12, position: 'relative', zIndex: 1 }}>
          Saldo pendente: {pending.toLocaleString()} (em processamento)
        </div>
      </div>

      {/* Action buttons */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 24 }}>
        <button style={{
          padding: '18px', borderRadius: 'var(--radius-lg)',
          background: 'var(--surface)', border: '1px solid var(--border)',
          display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8,
          cursor: 'pointer', transition: 'border-color .15s',
        }}
          onClick={() => setModalType('topup')}
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
          onClick={() => setModalType('withdraw')}
          onMouseEnter={e => e.currentTarget.style.borderColor = 'rgba(16,185,129,0.4)'}
          onMouseLeave={e => e.currentTarget.style.borderColor = 'var(--border)'}
        >
          <div style={{ width: 40, height: 40, borderRadius: 12, background: 'rgba(16,185,129,0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <TrendingUp size={20} color="#10b981" />
          </div>
          <span style={{ fontWeight: 600, fontSize: 14 }}>Sacar Ganhos</span>
        </button>
      </div>

      {/* Transactions */}
      <h2 style={{ fontWeight: 800, fontSize: 16, marginBottom: 14, display: 'flex', alignItems: 'center', gap: 6 }}>
        🏦 Histórico de transações
      </h2>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6, marginBottom: 32 }}>
        {history.map((t, idx) => (
          <div key={t.id || idx} style={{
            display: 'flex', alignItems: 'center', gap: 14,
            padding: '14px 16px', borderRadius: 'var(--radius)',
            background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)',
          }} className="glass-interactive"
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
            padding: '18px 10px', borderRadius: 'var(--radius)',
            background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)',
            display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6,
            cursor: 'pointer',
          }} className="glass-interactive"
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

      {/* ── MODAL ──────────────────────────────── */}
      {modalType && (
        <div style={{
          position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.6)',
          backdropFilter: 'blur(8px)', display: 'flex', alignItems: 'center',
          justifyContent: 'center', zIndex: 100, padding: 20
        }}>
          <div style={{
            background: 'var(--surface)', border: '1px solid var(--border)',
            borderRadius: 20, width: '100%', maxWidth: 440, padding: 28,
            position: 'relative', boxShadow: '0 20px 50px rgba(0,0,0,0.5)'
          }}>
            <button
              onClick={() => setModalType(null)}
              style={{ position: 'absolute', top: 20, right: 20, background: 'none', border: 'none', color: 'var(--muted)', cursor: 'pointer' }}
            >
              <X size={18} />
            </button>

            {modalType === 'topup' ? (
              <form onSubmit={handleTopUp}>
                <h3 style={{ fontSize: 18, fontWeight: 800, marginBottom: 8 }}>Recarregar Moedas 🪙</h3>
                <p style={{ color: 'var(--muted)', fontSize: 13, marginBottom: 20 }}>
                  Insira o valor em moedas que deseja recarregar. (Ambiente Local: Simulação de checkout Stripe).
                </p>
                <div style={{ marginBottom: 20 }}>
                  <label style={{ display: 'block', fontSize: 12, fontWeight: 700, marginBottom: 8, color: 'var(--text-2)' }}>Quantidade de Moedas</label>
                  <input
                    type="number"
                    required
                    min="10"
                    placeholder="Ex: 5000"
                    value={coinsInput}
                    onChange={e => setCoinsInput(e.target.value)}
                    style={{
                      width: '100%', background: 'rgba(255,255,255,0.03)', border: '1px solid var(--border)',
                      borderRadius: 12, padding: '12px 14px', color: '#fff', outline: 'none'
                    }}
                  />
                  <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 6 }}>
                    Custo estimado: ${((coinsInput || 0) / 142).toFixed(2)} USD
                  </div>
                </div>
                <button type="submit" style={{
                  width: '100%', padding: '14px', borderRadius: 24, background: 'var(--grad)',
                  color: '#fff', fontWeight: 700, fontSize: 14, cursor: 'pointer', border: 'none'
                }}>
                  Simular Pagamento Stripe
                </button>
              </form>
            ) : (
              <form onSubmit={handleWithdraw}>
                <h3 style={{ fontSize: 18, fontWeight: 800, marginBottom: 8 }}>Sacar Ganhos 🌿</h3>
                <p style={{ color: 'var(--muted)', fontSize: 13, marginBottom: 20 }}>
                  Solicite a transferência dos seus ganhos acumulados para sua conta de preferência.
                </p>
                <div style={{ marginBottom: 16 }}>
                  <label style={{ display: 'block', fontSize: 12, fontWeight: 700, marginBottom: 8, color: 'var(--text-2)' }}>Moedas para Sacar</label>
                  <input
                    type="number"
                    required
                    min="142"
                    max={balance}
                    placeholder={`Máx: ${balance}`}
                    value={coinsInput}
                    onChange={e => setCoinsInput(e.target.value)}
                    style={{
                      width: '100%', background: 'rgba(255,255,255,0.03)', border: '1px solid var(--border)',
                      borderRadius: 12, padding: '12px 14px', color: '#fff', outline: 'none'
                    }}
                  />
                  <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 6 }}>
                    Valor bruto a receber: ${((coinsInput || 0) / 142).toFixed(2)} USD
                  </div>
                </div>
                <div style={{ marginBottom: 16 }}>
                  <label style={{ display: 'block', fontSize: 12, fontWeight: 700, marginBottom: 8, color: 'var(--text-2)' }}>Método de Recebimento</label>
                  <select
                    value={withdrawMethod}
                    onChange={e => setWithdrawMethod(e.target.value)}
                    style={{
                      width: '100%', background: 'rgba(255,255,255,0.03)', border: '1px solid var(--border)',
                      borderRadius: 12, padding: '12px 14px', color: '#fff', outline: 'none'
                    }}
                  >
                    <option value="paypal" style={{ background: 'var(--surface)' }}>PayPal</option>
                    <option value="bank_transfer" style={{ background: 'var(--surface)' }}>Transferência Bancária</option>
                    <option value="stripe_express" style={{ background: 'var(--surface)' }}>Stripe Express</option>
                  </select>
                </div>
                <div style={{ marginBottom: 24 }}>
                  <label style={{ display: 'block', fontSize: 12, fontWeight: 700, marginBottom: 8, color: 'var(--text-2)' }}>Identificador da Conta (Email / Chave PIX)</label>
                  <input
                    type="text"
                    required
                    placeholder="Ex: seuemail@paypal.com"
                    value={withdrawAccount}
                    onChange={e => setWithdrawAccount(e.target.value)}
                    style={{
                      width: '100%', background: 'rgba(255,255,255,0.03)', border: '1px solid var(--border)',
                      borderRadius: 12, padding: '12px 14px', color: '#fff', outline: 'none'
                    }}
                  />
                </div>
                <button type="submit" style={{
                  width: '100%', padding: '14px', borderRadius: 24, background: '#10b981',
                  color: '#fff', fontWeight: 700, fontSize: 14, cursor: 'pointer', border: 'none'
                }}>
                  Solicitar Saque
                </button>
              </form>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
