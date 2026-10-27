import type { ImgHTMLAttributes } from 'react';

/** Logo We Smile (dent souriante). */
export default function AppLogoIcon({ className, ...props }: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src="/images/logo-wesmile.png" alt="We Smile" className={className} style={{ objectFit: 'contain' }} {...props} />;
}
