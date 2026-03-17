import './main-menu.scss';
import './main-menu.js';
import template from './main-menu.twig';
import { renderTwig } from '../../../config/storybook-utils/twig-renderer';

export default {
    title: 'Components/Main Menu',
    tags: ['autodocs'],
    render: (args) => renderTwig(template, args),
    argTypes: {},
};

export const Default = {
    name: 'Default',
    args: {
        items: [
            { title: "Home", url: "/", in_active_trail: true },
            { title: "School", url: "/", is_expanded: true },
            {
                title: "Student",
                is_expanded: true,
                below: [
                    { title: "Application Status", url: "/student/application-status" },
                    { title: "Application Print", url: "/student/application-print" }
                ]
            },
            {
                title: "Report",
                is_expanded: true,
                below: [
                    { title: "Neighbourhood Mapping" },
                    { title: "School Seat Info" }
                ]
            },
            {
                title: "Grievance",
                is_expanded: true,
                below: [
                    { title: "Grievance Status" },
                    { title: "Register Grievance" }
                ]
            }
        ]
    }
};