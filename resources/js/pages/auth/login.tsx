import { Form, Head } from '@inertiajs/react';
import { ArrowRight, Lock, Mail } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Sign in" />

            <div className="mb-6 space-y-1 text-center">
                <h2 className="text-2xl font-semibold tracking-tight text-neutral-900">
                    Welcome back!
                </h2>
                <p className="text-sm text-neutral-500">
                    Sign in to access your HRMS account
                </p>
            </div>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-[#00B8A3]">
                    {status}
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label
                                htmlFor="email"
                                className="text-sm font-medium text-neutral-700"
                            >
                                Email address
                            </Label>
                            <div className="relative">
                                <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-neutral-400" />
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="name@company.com"
                                    className="h-11 rounded-lg border-neutral-200 bg-white pl-10 text-neutral-900 placeholder:text-neutral-400 focus-visible:border-[#00B8A3] focus-visible:ring-[#00F5D4]/30"
                                />
                            </div>
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label
                                htmlFor="password"
                                className="text-sm font-medium text-neutral-700"
                            >
                                Password
                            </Label>
                            <div className="relative">
                                <Lock className="pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2 text-neutral-400" />
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Enter your password"
                                    className="h-11 rounded-lg border-neutral-200 bg-white pr-10 pl-10 text-neutral-900 placeholder:text-neutral-400 focus-visible:border-[#00B8A3] focus-visible:ring-[#00F5D4]/30"
                                />
                            </div>
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center justify-between">
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-neutral-600 select-none">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1"
                                    tabIndex={3}
                                    className="size-4 cursor-pointer rounded border-neutral-300 text-[#00B8A3] focus:ring-[#00B8A3]/30"
                                />
                                <span>Remember me</span>
                            </label>

                            {canResetPassword && (
                                <TextLink
                                    href={request()}
                                    className="text-sm font-medium text-[#00B8A3] hover:text-[#009688]"
                                    tabIndex={5}
                                >
                                    Forgot password?
                                </TextLink>
                            )}
                        </div>

                        <Button
                            type="submit"
                            className="relative mt-1 h-11 w-full rounded-lg bg-gradient-to-r from-[#002B5E] to-[#001A33] text-white hover:from-[#003A7A] hover:to-[#002B5E] focus-visible:ring-[#00F5D4]/40"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                        >
                            <span className="inline-flex items-center gap-2">
                                {processing && <Spinner />}
                                Sign in
                            </span>
                            {!processing && (
                                <ArrowRight className="absolute top-1/2 right-4 size-4 -translate-y-1/2 text-[#00F5D4]" />
                            )}
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: '',
    description: '',
};
