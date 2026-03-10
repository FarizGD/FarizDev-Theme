import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import { breakpoint } from '@/theme';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
};

const Container = styled.div`
    ${breakpoint('sm')`
        ${tw`w-4/5 mx-auto`}
    `};

    ${breakpoint('md')`
        ${tw`p-10`}
    `};

    ${breakpoint('lg')`
        ${tw`w-3/5`}
    `};

    ${breakpoint('xl')`
        ${tw`w-full`}
        max-width: 700px;
    `};
`;

const Card = styled.div`
    ${tw`w-full rounded-3xl p-6 md:p-10 md:flex gap-8 items-center`};
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 30px 70px rgba(8, 12, 24, 0.55);
    backdrop-filter: blur(22px);
    position: relative;
    overflow: hidden;

    &::before {
        content: '';
        position: absolute;
        inset: -60% -40% auto -40%;
        height: 220px;
        background: linear-gradient(120deg, rgba(255, 255, 255, 0.18), rgba(96, 165, 250, 0.22), rgba(59, 130, 246, 0.18));
        transform: translateY(-40%) rotate(8deg);
        opacity: 0.65;
        pointer-events: none;
    }
`;

const LogoPanel = styled.div`
    ${tw`flex-none select-none mb-6 md:mb-0 self-center text-center`};
    ${tw`px-4 py-6 rounded-2xl`};
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(16px);
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => (
    <Container>
        {title && <h2 css={tw`text-3xl text-center text-white font-semibold py-4`}>{title}</h2>}
        <FlashMessageRender css={tw`mb-2 px-1`} />
        <Form {...props} ref={ref}>
            <Card>
                <LogoPanel>
                    <img src={'/assets/svgs/pterodactyl.svg'} css={tw`block w-44 md:w-56 mx-auto`} />
                    <p css={tw`mt-4 text-sm text-white`}>Secure panel access</p>
                </LogoPanel>
                <div css={tw`flex-1 relative z-10`}>{props.children}</div>
            </Card>
        </Form>
        <p css={tw`text-center text-white text-xs mt-4 opacity-80`}>
            &copy; 2015 - {new Date().getFullYear()}&nbsp;
            <a
                rel={'noopener nofollow noreferrer'}
                href={'https://pterodactyl.io'}
                target={'_blank'}
                css={tw`no-underline text-white hover:text-white`}
            >
                Pterodactyl Software
            </a>
        </p>
    </Container>
));
