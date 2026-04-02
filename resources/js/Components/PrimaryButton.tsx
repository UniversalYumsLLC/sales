import { ButtonHTMLAttributes } from 'react';

export default function PrimaryButton({ className = '', disabled, children, ...props }: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            className={
                `rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold tracking-widest text-white ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:ring-indigo-500 active:bg-gray-900 inline-flex items-center border border-transparent uppercase transition duration-150 focus:ring-2 focus:ring-offset-2 focus:outline-none ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
