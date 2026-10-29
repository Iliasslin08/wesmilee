import { Component, type ErrorInfo, type ReactNode } from 'react';

/** Au lieu d'une page blanche : affiche l'erreur (à envoyer à l'administrateur) et propose de revenir en arrière. */
export default class ErreurAffichage extends Component<{ children: ReactNode }, { erreur: Error | null; pile: string }> {
    state = { erreur: null as Error | null, pile: '' };

    static getDerivedStateFromError(erreur: Error) {
        return { erreur, pile: '' };
    }

    componentDidCatch(erreur: Error, info: ErrorInfo) {
        this.setState({ pile: (info.componentStack ?? '').split('\n').filter(Boolean).slice(0, 6).join('\n') });
        console.error(erreur);
    }

    render() {
        if (!this.state.erreur) return this.props.children;
        return (
            <div style={{ maxWidth: 720, margin: '60px auto', padding: 24, fontFamily: 'Poppins, Arial, sans-serif', color: '#12303a' }}>
                <h1 style={{ color: '#b3261e', fontSize: 22, margin: '0 0 8px' }}>Cette page n’a pas pu s’afficher</h1>
                <p style={{ margin: '0 0 16px' }}>Faites une capture de cet écran et envoyez-la à l’administrateur de la plateforme.</p>
                <pre style={{ whiteSpace: 'pre-wrap', background: '#fff4f3', border: '1px solid #f3c7c3', borderRadius: 8, padding: 12, fontSize: 13 }}>
                    {window.location.pathname}
                    {'\n'}
                    {String(this.state.erreur?.message ?? this.state.erreur)}
                    {'\n\n'}
                    {this.state.pile}
                </pre>
                <div style={{ display: 'flex', gap: 12, marginTop: 16 }}>
                    <button onClick={() => (window.location.href = document.referrer && !document.referrer.endsWith(window.location.pathname) ? document.referrer : '/')} style={{ padding: '8px 16px', borderRadius: 8, border: '1px solid #cfe2e5', background: '#fff', cursor: 'pointer' }}>
                        ← Retour
                    </button>
                    <button onClick={() => window.location.reload()} style={{ padding: '8px 16px', borderRadius: 8, border: 0, background: '#04a3b5', color: '#fff', cursor: 'pointer' }}>
                        Recharger
                    </button>
                </div>
            </div>
        );
    }
}
