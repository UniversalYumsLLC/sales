import { ButtonHTMLAttributes } from 'react';

export default function DangerButton({ className = '', disabled, children, ...props }: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            className={
                `rounded-md bg-red-600 px-4 py-2 text-xs font-semibold tracking-widest text-white ease-in-out hover:bg-red-500 focus:ring-red-500 active:bg-red-700 inline-flex items-center border border-transparent uppercase transition duration-150 focus:ring-2 focus:ring-offset-2 focus:outline-none ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
