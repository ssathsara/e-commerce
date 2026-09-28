import { Component } from 'react'

/** Last-resort boundary so a rendering bug never leaves a blank screen. */
export class ErrorBoundary extends Component {
  state = { error: null }

  static getDerivedStateFromError(error) {
    return { error }
  }

  componentDidCatch(error, info) {
    if (import.meta.env.DEV) console.error(error, info)
  }

  render() {
    if (!this.state.error) return this.props.children

    return (
      <div role="alert" className="flex min-h-screen flex-col items-center justify-center gap-4 bg-slate-50 px-6 text-center">
        <h1 className="text-2xl font-bold text-slate-900">Something went wrong</h1>
        <p className="max-w-md text-slate-600">An unexpected error occurred. Please reload the page. If the problem persists, contact support.</p>
        <button type="button" onClick={() => window.location.reload()} className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
          Reload page
        </button>
      </div>
    )
  }
}
