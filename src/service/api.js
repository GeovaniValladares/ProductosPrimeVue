import axios from 'axios'
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: { Accept: 'application/json' },
})
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})
export const clearSession = () => {
  localStorage.removeItem('auth_token')
  localStorage.removeItem('user_data')
}
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      clearSession()
      if (location.pathname !== '/login') location.assign('/login')
    }
    return Promise.reject(error)
  },
)
const saveSession = (data) => {
  if (data.token) {
    localStorage.setItem('auth_token', data.token)
    localStorage.setItem('user_data', JSON.stringify(data.user))
  }
  return data
}
export const errorMessage = (error) =>
  Object.values(error.response?.data?.errors || {})
    .flat()
    .join(' ') ||
  error.response?.data?.message ||
  'No se pudo conectar con el servidor.'
export const useAuthService = () => ({
  login: async (credentials) => saveSession((await api.post('/login', credentials)).data),
  verify: async (payload) => saveSession((await api.post('/auth/verify', payload)).data),
  logout: async () => {
    try {
      await api.post('/logout')
    } finally {
      clearSession()
    }
  },
  getCurrentUser: async () => {
    const { data } = await api.get('/user')
    localStorage.setItem('user_data', JSON.stringify(data))
    return data
  },
  isAuthenticated: () => !!localStorage.getItem('auth_token'),
  getUserData: () => {
    try {
      return JSON.parse(localStorage.getItem('user_data'))
    } catch {
      return null
    }
  },
})
export const useProductoService = () => ({
  obtenerProductos: async () => (await api.get('/productos')).data,
})
export default api
