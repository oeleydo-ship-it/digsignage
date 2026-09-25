import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TeamInvitationAlert from '@/components/team-invitation-alert';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
import type { TeamInvitationContext } from '@/types';

type Props = {
    passwordRules: string;
    teamInvitation?: TeamInvitationContext | null;
    initialSetup?: boolean;
    setupConfigured?: boolean;
};

export default function Register({
    passwordRules,
    teamInvitation,
    initialSetup = false,
    setupConfigured = false,
}: Props) {
    return (
        <>
            <Head title={initialSetup ? 'Set up administrator' : 'Register'} />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        {initialSetup && (
                            <div className="border-border bg-muted/40 rounded-lg border p-4 text-sm">
                                <p className="font-semibold">
                                    Set up the platform administrator
                                </p>
                                <p className="text-muted-foreground mt-1">
                                    This is a new installation. Create the first
                                    administrator before the site opens to other
                                    users. Enter the setup key configured on the
                                    server.
                                </p>
                                {!setupConfigured && (
                                    <p className="text-destructive mt-2">
                                        The server owner must set
                                        INITIAL_ADMIN_SETUP_KEY before this form
                                        can be submitted.
                                    </p>
                                )}
                            </div>
                        )}
                        {teamInvitation && (
                            <>
                                <TeamInvitationAlert
                                    invitation={teamInvitation}
                                    action="Register"
                                />
                                {/* Lets invited people register while public sign-ups are closed. */}
                                <input
                                    type="hidden"
                                    name="invitation"
                                    value={teamInvitation.code}
                                />
                            </>
                        )}

                        <div className="grid gap-6">
                            {initialSetup && (
                                <div className="grid gap-2">
                                    <Label htmlFor="setup_key">
                                        Server setup key
                                    </Label>
                                    <Input
                                        id="setup_key"
                                        type="password"
                                        required
                                        autoComplete="off"
                                        name="setup_key"
                                        placeholder="Enter the one-time setup key"
                                    />
                                    <InputError message={errors.setup_key} />
                                </div>
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Full name"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={5}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                {initialSetup
                                    ? 'Create administrator'
                                    : 'Create account'}
                            </Button>
                        </div>

                        {!initialSetup && (
                            <div className="text-muted-foreground text-center text-sm">
                                Already have an account?{' '}
                                <TextLink
                                    href={
                                        teamInvitation
                                            ? login.url({
                                                  query: {
                                                      invitation:
                                                          teamInvitation.code,
                                                  },
                                              })
                                            : login()
                                    }
                                    data-test="team-invitation-login-link"
                                    tabIndex={6}
                                >
                                    Log in
                                </TextLink>
                            </div>
                        )}
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Create an account',
    description: 'Enter your details below to create your account',
};
