import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function NavLink({ active = false, className = '', children, ...props }: InertiaLinkProps & { active: boolean }) {
    return (
        <Link
            {...props}
            className={
                'px-1 pt-1 text-sm font-medium leading-5 ease-in-out inline-flex items-center border-b-2 transition duration-150 focus:outline-none ' +
                (active
                    ? 'border-indigo-400 text-gray-900 focus:border-indigo-700'
                    : 'text-gray-500 hover:border-gray-300 hover:text-gray-700 focus:border-gray-300 focus:text-gray-700 border-transparent') +
                className
            }
        >
            {children}
        </Link>
    );
}
