import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function ResponsiveNavLink({ active = false, className = '', children, ...props }: InertiaLinkProps & { active?: boolean }) {
    return (
        <Link
            {...props}
            className={`py-2 pe-4 ps-3 flex w-full items-start border-l-4 ${
                active
                    ? 'border-indigo-400 bg-indigo-50 text-indigo-700 focus:border-indigo-700 focus:bg-indigo-100 focus:text-indigo-800'
                    : 'text-gray-600 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 focus:border-gray-300 focus:bg-gray-50 focus:text-gray-800 border-transparent'
            } text-base font-medium ease-in-out transition duration-150 focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
