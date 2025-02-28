import React from 'react';
import '@testing-library/jest-dom/extend-expect';
import {screen, waitFor} from '@testing-library/react';
import fetchMock from 'jest-fetch-mock';
import {renderWithProviders, historyMock} from '../../../../test-utils';
import {ConnectedAppCard} from '@src/connect/components/ConnectedApps/ConnectedAppCard';
import {SecurityContext} from '@src/shared/security';
import {AppIllustration} from 'akeneo-design-system';

beforeEach(() => {
    fetchMock.resetMocks();
    historyMock.reset();
});

jest.mock('akeneo-design-system', () => ({
    ...jest.requireActual('akeneo-design-system'),
    AppIllustration: jest.fn(() => null),
}));

test('The connected app card renders', async () => {
    const item = {
        id: '0dfce574-2238-4b13-b8cc-8d257ce7645b',
        name: 'App A',
        scopes: ['scope A1'],
        connection_code: 'connectionCodeA',
        logo: 'http://www.example.test/path/to/logo/a',
        author: 'author A',
        user_group_name: 'app_123456abcde',
        categories: ['category A1', 'category A2'],
        certified: false,
        partner: 'partner A',
        activate_url: 'http://www.example.com/activate',
        is_test_app: false,
    };

    renderWithProviders(<ConnectedAppCard item={item} />);
    await waitFor(() => screen.getByText('App A'));

    expect(screen.queryByText('App A')).toBeInTheDocument();
    expect(
        screen.queryByText(
            'akeneo_connectivity.connection.connect.connected_apps.list.card.developed_by?author=author+A'
        )
    ).toBeInTheDocument();
    expect(screen.queryByText('category A1')).toBeInTheDocument();
    expect(screen.queryByText('category A2')).toBeNull();
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.connected_apps.list.card.manage_app')
    ).toBeInTheDocument();
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.connected_apps.list.card.open_app')
    ).toBeInTheDocument();
});

test('The Manage App button is disabled when the user doesnt have the permission to Manage Apps', async () => {
    const isGranted = jest.fn(acl => {
        if (acl === 'akeneo_connectivity_connection_manage_apps') {
            return false;
        }
        return true;
    });

    const item = {
        id: '0dfce574-2238-4b13-b8cc-8d257ce7645b',
        name: 'App A',
        scopes: ['scope A1'],
        connection_code: 'connectionCodeA',
        logo: 'http://www.example.test/path/to/logo/a',
        author: 'author A',
        user_group_name: 'app_123456abcde',
        categories: ['category A1', 'category A2'],
        certified: false,
        partner: 'partner A',
        activate_url: 'http://www.example.com/activate',
        is_test_app: false,
    };

    renderWithProviders(
        <SecurityContext.Provider value={{isGranted}}>
            <ConnectedAppCard item={item} />
        </SecurityContext.Provider>
    );
    await waitFor(() => screen.getByText('App A'));

    const manageAppButton = expect(
        screen.queryByText('akeneo_connectivity.connection.connect.connected_apps.list.card.manage_app')
    );
    manageAppButton.not.toHaveAttribute('href');
    manageAppButton.toHaveAttribute('disabled');
    manageAppButton.toHaveAttribute('aria-disabled', 'true');
});

test('The Open App button is disabled when the user doesnt have the permission to Open Apps', async () => {
    const isGranted = jest.fn(acl => {
        if (acl === 'akeneo_connectivity_connection_open_apps') {
            return false;
        }
        return true;
    });

    const item = {
        id: '0dfce574-2238-4b13-b8cc-8d257ce7645b',
        name: 'App A',
        scopes: ['scope A1'],
        connection_code: 'connectionCodeA',
        logo: 'http://www.example.test/path/to/logo/a',
        author: 'author A',
        user_group_name: 'app_123456abcde',
        categories: ['category A1', 'category A2'],
        certified: false,
        partner: 'partner A',
        activate_url: 'http://www.example.com/activate',
        is_test_app: false,
    };

    renderWithProviders(
        <SecurityContext.Provider value={{isGranted}}>
            <ConnectedAppCard item={item} />
        </SecurityContext.Provider>
    );
    await waitFor(() => screen.getByText('App A'));

    const openAppButton = expect(
        screen.queryByText('akeneo_connectivity.connection.connect.connected_apps.list.card.open_app')
    );
    openAppButton.not.toHaveAttribute('href');
    openAppButton.toHaveAttribute('disabled');
    openAppButton.toHaveAttribute('aria-disabled', 'true');
});

test('The Open App and Manage App buttons are enabled for test app when the user has the permission to manage test apps', async () => {
    const isGranted = jest.fn(acl => {
        if (acl === 'akeneo_connectivity_connection_manage_test_apps') {
            return true;
        }
        return false;
    });

    const item = {
        id: '0dfce574-2238-4b13-b8cc-8d257ce7645b',
        name: 'App A',
        scopes: ['scope A1'],
        connection_code: 'connectionCodeA',
        logo: 'http://www.example.test/path/to/logo/a',
        author: 'author A',
        user_group_name: 'app_123456abcde',
        categories: ['category A1', 'category A2'],
        certified: false,
        partner: 'partner A',
        activate_url: 'http://www.example.com/activate',
        is_test_app: true,
    };

    renderWithProviders(
        <SecurityContext.Provider value={{isGranted}}>
            <ConnectedAppCard item={item} />
        </SecurityContext.Provider>
    );
    await waitFor(() => screen.getByText('App A'));

    const openAppButton = expect(
        screen.queryByText('akeneo_connectivity.connection.connect.connected_apps.list.card.open_app')
    );
    openAppButton.toHaveAttribute('href', 'http://www.example.com/activate');
    openAppButton.not.toHaveAttribute('disabled');
    openAppButton.not.toHaveAttribute('aria-disabled', 'true');

    const manageAppButton = expect(
        screen.queryByText('akeneo_connectivity.connection.connect.connected_apps.list.card.manage_app')
    );
    manageAppButton.toHaveAttribute(
        'href',
        '#akeneo_connectivity_connection_connect_connected_apps_edit?connectionCode=connectionCodeA'
    );
    manageAppButton.not.toHaveAttribute('disabled');
    manageAppButton.not.toHaveAttribute('aria-disabled', 'true');
});

test('The connected app card displays removed user as author when author is null', async () => {
    const item = {
        id: '0dfce574-2238-4b13-b8cc-8d257ce7645b',
        name: 'App A',
        scopes: ['scope A1'],
        connection_code: 'connectionCodeA',
        logo: 'http://www.example.test/path/to/logo/a',
        author: null,
        user_group_name: 'app_123456abcde',
        categories: ['category A1', 'category A2'],
        certified: false,
        partner: 'partner A',
        activate_url: 'http://www.example.com/activate',
        is_test_app: false,
    };

    renderWithProviders(<ConnectedAppCard item={item} />);
    await waitFor(() => screen.getByText('App A'));

    expect(screen.queryByText('App A')).toBeInTheDocument();
    expect(
        screen.queryByText(
            'akeneo_connectivity.connection.connect.connected_apps.list.card.developed_by?author=akeneo_connectivity.connection.connect.connected_apps.list.test_apps.removed_user'
        )
    ).toBeInTheDocument();
});

test('The connected app card displays app illustration when logo is null', async () => {
    const item = {
        id: '0dfce574-2238-4b13-b8cc-8d257ce7645b',
        name: 'App A',
        scopes: ['scope A1'],
        connection_code: 'connectionCodeA',
        logo: null,
        author: 'author A',
        user_group_name: 'app_123456abcde',
        categories: ['category A1', 'category A2'],
        certified: false,
        partner: 'partner A',
        activate_url: 'http://www.example.com/activate',
        is_test_app: false,
    };

    renderWithProviders(<ConnectedAppCard item={item} />);
    await waitFor(() => screen.getByText('App A'));

    expect(screen.queryByText('App A')).toBeInTheDocument();
    expect(screen.queryByAltText('App A')).not.toBeInTheDocument();
    expect(AppIllustration).toHaveBeenCalled();
});
