import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, X, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Shows the session flash message from the last request, then fades it out.
 */
export function FlashToast() {
    const { flash } = usePage<SharedData>().props;
    const [message, setMessage] = useState<{ text: string; error: boolean } | null>(null);

    useEffect(() => {
        const text = flash.error ?? flash.success;
        if (!text) return;

        setMessage({ text, error: !!flash.error });
        const timer = setTimeout(() => setMessage(null), 4000);
        return () => clearTimeout(timer);
    }, [flash]);

    if (!message) return null;

    return (
        <div className="fixed inset-x-0 bottom-4 z-50 flex justify-center px-4" role="status">
            <div className="bg-foreground text-background animate-in fade-in slide-in-from-bottom-2 flex max-w-md items-center gap-2 rounded-lg px-4 py-3 text-sm shadow-lg">
                {message.error ? <XCircle className="size-4 text-red-400" /> : <CheckCircle2 className="size-4 text-emerald-400" />}
                <span>{message.text}</span>
                <button onClick={() => setMessage(null)} className="ml-2 opacity-60 hover:opacity-100" aria-label="Dismiss">
                    <X className="size-4" />
                </button>
            </div>
        </div>
    );
}
