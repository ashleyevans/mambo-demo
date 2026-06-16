import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { redirect as googleRedirect } from '@/routes/auth/google';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
                <div className="flex w-full max-w-sm flex-col items-center">
                    <Card className="w-full">
                        <CardContent className="flex flex-col items-center gap-8">
                            <img
                                src="/images/logo.png"
                                alt="Logo"
                                className="h-10 w-auto dark:invert"
                            />

                            {auth.user ? (
                                <Button asChild className="w-full">
                                    <Link href={dashboard()}>
                                        Go to dashboard
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="w-full"
                                >
                                    <a href={googleRedirect.url()}>
                                        <svg
                                            className="size-4"
                                            viewBox="0 0 24 24"
                                            xmlns="http://www.w3.org/2000/svg"
                                            aria-hidden="true"
                                        >
                                            <path
                                                fill="#4285F4"
                                                d="M23.52 12.273c0-.851-.076-1.67-.218-2.455H12v4.642h6.458a5.52 5.52 0 0 1-2.396 3.622v3.01h3.878c2.27-2.09 3.58-5.17 3.58-8.82Z"
                                            />
                                            <path
                                                fill="#34A853"
                                                d="M12 24c3.24 0 5.956-1.075 7.94-2.908l-3.878-3.01c-1.075.72-2.45 1.145-4.062 1.145-3.125 0-5.77-2.11-6.713-4.945H1.28v3.11A11.997 11.997 0 0 0 12 24Z"
                                            />
                                            <path
                                                fill="#FBBC05"
                                                d="M5.287 14.282A7.207 7.207 0 0 1 4.91 12c0-.792.137-1.562.377-2.282V6.608H1.28A11.997 11.997 0 0 0 0 12c0 1.936.464 3.768 1.28 5.392l4.007-3.11Z"
                                            />
                                            <path
                                                fill="#EA4335"
                                                d="M12 4.773c1.762 0 3.344.606 4.59 1.795l3.44-3.44C17.952 1.19 15.236 0 12 0A11.997 11.997 0 0 0 1.28 6.608l4.007 3.11C6.23 6.882 8.875 4.773 12 4.773Z"
                                            />
                                        </svg>
                                        Sign in with Google
                                    </a>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
