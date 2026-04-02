import { ButtonHTMLAttributes } from 'react';

export default function SecondaryButton({ type = 'button', className = '', disabled, children, ...props }: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            type={type}
            className={
                `rounded-md border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 shadow-sm ease-in-out hover:bg-gray-50 focus:ring-indigo-500 inline-flex items-center border uppercase transition duration-150 focus:ring-2 focus:ring-offset-2 focus:outline-none disabled:opacity-25 ${
                    disabled && 'opacity-25'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
