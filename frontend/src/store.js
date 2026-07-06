import { create } from 'zustand'

export const useAuthStore = create(set => ({
  user: JSON.parse(localStorage.getItem('user') || 'null'),
  token: localStorage.getItem('access_token') || null,

  login(user, token, refresh) {
    localStorage.setItem('user', JSON.stringify(user))
    localStorage.setItem('access_token', token)
    localStorage.setItem('refresh_token', refresh)
    set({ user, token })
  },

  logout() {
    localStorage.clear()
    set({ user: null, token: null })
  },
}))
