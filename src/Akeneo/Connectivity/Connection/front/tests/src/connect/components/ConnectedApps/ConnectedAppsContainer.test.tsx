import React from 'react';
import '@testing-library/jest-dom/extend-expect';
import {screen, waitFor} from '@testing-library/react';
import fetchMock from 'jest-fetch-mock';
import {historyMock, renderWithProviders} from '../../../../test-utils';
import {ConnectedAppsContainer} from '@src/connect/components/ConnectedApps/ConnectedAppsContainer';
import ConnectedAppsContainerHelper from '@src/connect/components/ConnectedApps/ConnectedAppsContainerHelper';
import {ConnectedTestAppList} from '@src/connect/components/ConnectedApps/ConnectedTestAppList';
import {ConnectedAppCard} from '@src/connect/components/ConnectedApps/ConnectedAppCard';

beforeEach(() => {
    fetchMock.resetMocks();
    historyMock.reset();
    jest.clearAllMocks();
});

jest.mock('@src/shared/feature-flags/use-feature-flags', () => ({
    useFeatureFlags: () => {
        return {
            isEnabled: () => true,
        };
    },
}));

jest.mock('@src/connect/components/ConnectedApps/ConnectedAppsContainerHelper', () => ({
    ...jest.requireActual('@src/connect/components/ConnectedApps/ConnectedAppsContainerHelper'),
    __esModule: true,
    default: jest.fn(() => <h1>Helper mock</h1>),
}));

jest.mock('@src/connect/components/ConnectedApps/ConnectedAppCard', () => ({
    ...jest.requireActual('@src/connect/components/ConnectedApps/ConnectedAppCard'),
    ConnectedAppCard: jest.fn(() => null),
}));

jest.mock('@src/connect/components/ConnectedApps/ConnectedTestAppList', () => ({
    ...jest.requireActual('@src/connect/components/ConnectedApps/ConnectedTestAppList'),
    ConnectedTestAppList: jest.fn(() => null),
}));

const connectedApps = [
    {
        id: 'app_id_a',
        name: 'App A',
        scopes: [],
        connection_code: 'connectionCodeA',
        logo: 'http://www.example.test/path/to/logo/a',
        author: 'author A',
        user_group_name: 'user_group_a',
        categories: [],
        certified: true,
        partner: null,
        is_test_app: false,
    },
    {
        id: 'app_id_b',
        name: 'App B',
        scopes: [],
        connection_code: 'connectionCodeB',
        logo: 'http://www.example.test/path/to/logo/b',
        author: 'author B',
        user_group_name: 'user_group_b',
        categories: [],
        certified: true,
        partner: null,
        is_test_app: true,
    },
    {
        id: 'app_id_c',
        name: 'App C',
        scopes: [],
        connection_code: 'connectionCodeC',
        logo: 'http://www.example.test/path/to/logo/c',
        author: 'author C',
        user_group_name: 'user_group_c',
        categories: [],
        certified: true,
        partner: null,
        is_test_app: true,
    },
    {
        id: 'app_id_d',
        name: 'App D',
        scopes: [],
        connection_code: 'connectionCodeD',
        logo: 'http://www.example.test/path/to/logo/d',
        author: 'author D',
        user_group_name: 'user_group_d',
        categories: [],
        certified: true,
        partner: null,
        is_test_app: false,
    },
];

test('The connected apps list renders with 2 connected apps card and 2 connected test app', async () => {
    renderWithProviders(<ConnectedAppsContainer allConnectedApps={connectedApps} />);
    await waitFor(() => screen.getByText('Helper mock'));

    expect(ConnectedAppsContainerHelper).toBeCalledWith({count: 4}, {});

    expect(ConnectedTestAppList).toHaveBeenCalledWith(
        {
            connectedTestApps: [connectedApps[1], connectedApps[2]],
        },
        {}
    );

    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.connected_apps.list.apps.title')
    ).toBeInTheDocument();
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.connected_apps.list.apps.total?total=2')
    ).toBeInTheDocument();

    expect(ConnectedAppCard).toHaveBeenNthCalledWith(1, {item: connectedApps[0]}, {});
    expect(ConnectedAppCard).toHaveBeenNthCalledWith(2, {item: connectedApps[3]}, {});
});

test('The connected apps list renders without connected apps', async () => {
    renderWithProviders(<ConnectedAppsContainer allConnectedApps={[]} />);
    await waitFor(() => screen.getByText('Helper mock'));

    expect(ConnectedAppsContainerHelper).toBeCalledWith({count: 0}, {});

    expect(ConnectedTestAppList).toHaveBeenCalledWith({connectedTestApps: []}, {});

    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.connected_apps.list.apps.title')
    ).toBeInTheDocument();
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.connected_apps.list.apps.total?total=0')
    ).toBeInTheDocument();

    expect(ConnectedAppCard).not.toHaveBeenCalled();

    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.connected_apps.list.apps.empty')
    ).toBeInTheDocument();
    expect(
        screen.queryByText('akeneo_connectivity.connection.connect.connected_apps.list.apps.check_marketplace', {
            exact: false,
        })
    ).toBeInTheDocument();
    expect(
        screen.queryAllByText('akeneo_connectivity.connection.connect.connected_apps.list.card.manage_app')
    ).toHaveLength(0);
});
