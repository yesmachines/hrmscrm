import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 40 40"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M8 8h5.5v9.25L22.25 8H28l-9.5 11.25L28 30.5h-5.75L13.5 20.75V30.5H8V8zm20.5 0H34v22.5h-5.5V8z"
            />
        </svg>
    );
}
