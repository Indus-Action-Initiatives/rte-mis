import "./book-a-seat-block.scss";
import template from "./book-a-seat-block.twig";
import { renderTwig } from "../../../config/storybook-utils/twig-renderer";

export default {
  title: "Components/Book A Seat Block",
  render: (args) => renderTwig(template, args),
  argTypes: {
    title: { control: "text" },
    description: { control: "text" },
    buttons: { control: "object" },
    background_image: { control: "text" },
  },
};

export const Default = {
  name: "Default",
  args: {
    title: "Book A Seat Now",
    description:
      "Join thousands of families who have transformed their children's future through quality education",
    buttons: [
      { text: "Check Documents & Guidelines", link: "#" },
    ],
    background_image:
      "https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=1200&q=80",
  },
};

export const MultipleButtons = {
  name: "Multiple Buttons",
  args: {
    ...Default.args,
    buttons: [
      { text: "Check Documents & Guidelines", link: "#" },
      { text: "Apply Now", link: "#apply" },
    ],
  },
};
