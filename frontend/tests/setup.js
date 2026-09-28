import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach, vi } from 'vitest'

// Node 25+ exposes its own experimental localStorage global, which shadows jsdom's
// and is unusable without --localstorage-file. Provide a spec-compliant in-memory one.
class MemoryStorage {
  #data = new Map()
  get length() {
    return this.#data.size
  }
  key(index) {
    return [...this.#data.keys()][index] ?? null
  }
  getItem(key) {
    return this.#data.has(key) ? this.#data.get(key) : null
  }
  setItem(key, value) {
    this.#data.set(key, String(value))
  }
  removeItem(key) {
    this.#data.delete(key)
  }
  clear() {
    this.#data.clear()
  }
}

for (const name of ['localStorage', 'sessionStorage']) {
  Object.defineProperty(window, name, { value: new MemoryStorage(), configurable: true, writable: true })
  Object.defineProperty(globalThis, name, { value: window[name], configurable: true, writable: true })
}

afterEach(() => {
  cleanup()
  vi.clearAllMocks()
  window.localStorage.clear()
})

// jsdom does not implement these browser APIs used by the UI.
window.scrollTo = () => {}
if (!window.matchMedia) {
  window.matchMedia = () => ({ matches: false, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {} })
}
if (!globalThis.ResizeObserver) {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  }
}
