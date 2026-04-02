import "./book-a-seat-block.scss";
import template from "./book-a-seat-block.twig";
import { renderTwig } from "../../../config/storybook-utils/twig-renderer";

export default {
  title: "Components/Book A Seat Block",
  render: (args) => renderTwig(template, args),
  argTypes: {
    title: { control: "text" },
    description: { control: "text" },
    button_text: { control: "text" },
    button_link: { control: "text" },
    background_image: { control: "text" },
  },
};

export const Default = {
  name: "Default",
  args: {
    title: "Book A Seat Now",
    description:
      "Join thousands of families who have transformed their children's future through quality education",
    button_text: "Check Documents & Guidelines",
    button_link: "#",
    background_image:
      "https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=1200&q=80",
  },
};

export const WithGeneratedImage = {
  name: "With Generated Image",
  args: {
    ...Default.args,
    background_image:
      "https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=1200&q=80https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=1200&q=80/@fs/Users/akshay/.gemini/antigravity/brain/4e497e92-334e-41f8-83f5-a551faffa993/right_to_education_bg_1774715706021.png",
  },
};
