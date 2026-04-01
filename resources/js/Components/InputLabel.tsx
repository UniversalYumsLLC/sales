import { LabelHTMLAttributes } from 'react';

export default function InputLabel({ value, className = '', children, ...props }: LabelHTMLAttributes<HTMLLabelElement> & { value?: string }) {
    return (
        <label {...props} className={`text-sm font-medium text-gray-700 block ` + className}>
            {value ? value : children}
        </label>
    );
}
