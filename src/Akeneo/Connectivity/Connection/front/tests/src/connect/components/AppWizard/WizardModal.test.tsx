import {WizardModal} from '@src/connect/components/AppWizard/WizardModal';
import '@testing-library/jest-dom/extend-expect';
import {fireEvent, screen} from '@testing-library/react';
import React from 'react';
import {act} from 'react-dom/test-utils';
import {renderWithProviders} from '../../../../test-utils';
import userEvent from '@testing-library/user-event';

const handleClose = jest.fn();
const handleConfirm = jest.fn();
const renderChildren = jest.fn();

beforeEach(() => {
    jest.clearAllMocks();
});

test('it renders a single step wizard modal', () => {
    renderWithProviders(
        <WizardModal
            appLogo='MyAppLogo'
            appName='MyApp'
            onClose={handleClose}
            onConfirm={handleConfirm}
            steps={[
                {
                    name: 'authentication',
                    action: 'confirm',
                },
            ]}
        >
            {renderChildren}
        </WizardModal>
    );

    expect(renderChildren).toBeCalledWith({
        name: 'authentication',
        action: 'confirm',
    });

    expect(screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.cancel')).toBeInTheDocument();
    expect(screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.confirm')).toBeInTheDocument();
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.previous')
    ).not.toBeInTheDocument();
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.progress.authentication')
    ).not.toBeInTheDocument();

    act(() => {
        fireEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.cancel'));
    });
    expect(handleClose).toBeCalled();

    act(() => {
        fireEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.confirm'));
    });
    expect(handleConfirm).toBeCalled();
});

test('it renders a multi steps wizard modal', () => {
    renderWithProviders(
        <WizardModal
            appLogo='MyAppLogo'
            appName='MyApp'
            onClose={handleClose}
            onConfirm={handleConfirm}
            steps={[
                {
                    name: 'authentication',
                    action: 'next',
                },
                {
                    name: 'authorizations',
                    action: 'confirm',
                },
            ]}
        >
            {renderChildren}
        </WizardModal>
    );

    expect(renderChildren).toBeCalledWith({
        name: 'authentication',
        action: 'next',
    });

    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.previous')
    ).not.toBeInTheDocument();
    expect(screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.next')).toBeInTheDocument();
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.progress.authentication')
    ).toBeInTheDocument();
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.cancel')
    ).not.toBeInTheDocument();
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.confirm')
    ).not.toBeInTheDocument();

    act(() => {
        fireEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.next'));
    });

    expect(renderChildren).toBeCalledWith({
        name: 'authorizations',
        action: 'confirm',
    });

    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.previous')
    ).toBeInTheDocument();
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.next')
    ).not.toBeInTheDocument();
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.progress.authorizations')
    ).toBeInTheDocument();
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.cancel')
    ).not.toBeInTheDocument();
    expect(screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.confirm')).toBeInTheDocument();

    act(() => {
        fireEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.confirm'));
    });
    expect(handleConfirm).toBeCalled();
});

test('it prevents going further than first step', () => {
    renderWithProviders(
        <WizardModal
            appLogo='MyAppLogo'
            appName='MyApp'
            onClose={handleClose}
            onConfirm={handleConfirm}
            maxAllowedStep={'authentication'}
            steps={[
                {
                    name: 'authentication',
                    action: 'next',
                },
                {
                    name: 'authorizations',
                    action: 'confirm',
                },
            ]}
        >
            {renderChildren}
        </WizardModal>
    );

    expect(renderChildren).toBeCalledWith({name: 'authentication', action: 'next'});

    act(() => {
        userEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.next'));
    });

    expect(renderChildren).not.toBeCalledWith({name: 'authorizations', action: 'confirm'});

    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.confirm')
    ).not.toBeInTheDocument();
    expect(handleConfirm).not.toBeCalled();
});

test('it prevents going further than max allowed step', () => {
    renderWithProviders(
        <WizardModal
            appLogo='MyAppLogo'
            appName='MyApp'
            onClose={handleClose}
            onConfirm={handleConfirm}
            maxAllowedStep={'permissions'}
            steps={[
                {
                    name: 'authentication',
                    action: 'allow_and_next',
                },
                {
                    name: 'permissions',
                    action: 'next',
                },
                {
                    name: 'authorizations',
                    action: 'confirm',
                },
            ]}
        >
            {renderChildren}
        </WizardModal>
    );

    expect(renderChildren).toBeCalledWith({name: 'authentication', action: 'allow_and_next'});

    act(() => {
        userEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.allow_and_next'));
    });
    expect(renderChildren).toBeCalledWith({name: 'permissions', action: 'next'});

    act(() => {
        userEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.next'));
    });
    expect(renderChildren).not.toBeCalledWith({name: 'authorizations', action: 'confirm'});
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.confirm')
    ).not.toBeInTheDocument();

    act(() => {
        userEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.previous'));
    });
    expect(renderChildren).toBeCalledWith({name: 'authentication', action: 'allow_and_next'});
    act(() => {
        userEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.allow_and_next'));
    });
    expect(renderChildren).toBeCalledWith({name: 'permissions', action: 'next'});

    act(() => {
        userEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.next'));
    });
    expect(renderChildren).not.toBeCalledWith({name: 'authorizations', action: 'confirm'});
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.confirm')
    ).not.toBeInTheDocument();
    expect(handleConfirm).not.toBeCalled();
});

test('it prevents confirming on last step', () => {
    renderWithProviders(
        <WizardModal
            appLogo='MyAppLogo'
            appName='MyApp'
            onClose={handleClose}
            onConfirm={handleConfirm}
            maxAllowedStep={'authorizations'}
            steps={[
                {
                    name: 'authentication',
                    action: 'allow_and_next',
                },
                {
                    name: 'permissions',
                    action: 'next',
                },
                {
                    name: 'authorizations',
                    action: 'confirm',
                },
            ]}
        >
            {renderChildren}
        </WizardModal>
    );

    expect(renderChildren).toBeCalledWith({name: 'authentication', action: 'allow_and_next'});
    act(() => {
        userEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.allow_and_next'));
    });
    expect(renderChildren).toBeCalledWith({name: 'permissions', action: 'next'});

    act(() => {
        userEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.next'));
    });
    expect(renderChildren).toBeCalledWith({name: 'authorizations', action: 'confirm'});
    expect(screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.confirm')).toBeInTheDocument();

    act(() => {
        userEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.confirm'));
    });

    expect(handleConfirm).not.toBeCalled();
});

test('it is able to confirm with unknown max allowed step', () => {
    renderWithProviders(
        <WizardModal
            appLogo='MyAppLogo'
            appName='MyApp'
            onClose={handleClose}
            onConfirm={handleConfirm}
            maxAllowedStep={'summary'}
            steps={[
                {
                    name: 'authentication',
                    action: 'allow_and_next',
                },
                {
                    name: 'permissions',
                    action: 'next',
                },
                {
                    name: 'authorizations',
                    action: 'confirm',
                },
            ]}
        >
            {renderChildren}
        </WizardModal>
    );

    expect(renderChildren).toBeCalledWith({name: 'authentication', action: 'allow_and_next'});
    act(() => {
        userEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.allow_and_next'));
    });
    expect(renderChildren).toBeCalledWith({name: 'permissions', action: 'next'});

    act(() => {
        userEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.next'));
    });
    expect(renderChildren).toBeCalledWith({name: 'authorizations', action: 'confirm'});
    expect(screen.queryByText('akeneo_connectivity.connection.connect.apps.wizard.action.confirm')).toBeInTheDocument();

    act(() => {
        userEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.confirm'));
    });

    expect(handleConfirm).toBeCalled();
});

test('it prevents confirming with a single step', () => {
    renderWithProviders(
        <WizardModal
            appLogo='MyAppLogo'
            appName='MyApp'
            onClose={handleClose}
            onConfirm={handleConfirm}
            maxAllowedStep={'authentication'}
            steps={[
                {
                    name: 'authentication',
                    action: 'confirm',
                },
            ]}
        >
            {renderChildren}
        </WizardModal>
    );

    expect(renderChildren).toBeCalledWith({name: 'authentication', action: 'confirm'});

    act(() => {
        userEvent.click(screen.getByText('akeneo_connectivity.connection.connect.apps.wizard.action.confirm'));
    });

    expect(handleConfirm).not.toBeCalled();
});
