import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="bg-gray-100 pt-6 sm:justify-center sm:pt-0 flex min-h-screen flex-col items-center">
            <div>
                <Link href="/">
                    <ApplicationLogo className="h-20 w-20 text-gray-500 fill-current" />
                </Link>
            </div>

            <div className="mt-6 bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg w-full overflow-hidden">{children}</div>
        </div>
    );
}
