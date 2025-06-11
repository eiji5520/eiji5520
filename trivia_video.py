"""Trivia Video Creation Script
==============================

This script retrieves trivia questions from the Open Trivia DB API, generates
speech audio using gTTS, creates slide images with Pillow, and composes a video
with MoviePy.
"""

from __future__ import annotations

import os
import json

try:
    import requests
except ImportError as exc:  # pragma: no cover
    raise ImportError("The 'requests' package is required. Install it via 'pip install requests'") from exc

try:
    from gtts import gTTS
except ImportError as exc:  # pragma: no cover
    raise ImportError("The 'gTTS' package is required. Install it via 'pip install gTTS'") from exc

try:
    from PIL import Image, ImageDraw, ImageFont
except ImportError as exc:  # pragma: no cover
    raise ImportError("The 'Pillow' package is required. Install it via 'pip install Pillow'") from exc

try:
    from moviepy.editor import ImageClip, AudioFileClip, concatenate_videoclips
except ImportError as exc:  # pragma: no cover
    raise ImportError("The 'moviepy' package is required. Install it via 'pip install moviepy'") from exc


def get_trivia_questions(num_questions: int = 3) -> list[dict[str, str]]:
    """Retrieve trivia questions from the Open Trivia DB API.

    Parameters
    ----------
    num_questions : int, optional
        Number of trivia questions to retrieve, by default 3.

    Returns
    -------
    list[dict[str, str]]
        A list of dictionaries containing 'question' and 'answer' keys.
    """
    url = f"https://opentdb.com/api.php?amount={num_questions}&type=multiple"
    response = requests.get(url, timeout=10)
    response.raise_for_status()
    data = response.json()
    trivia_list = []
    for item in data.get("results", []):
        question = item.get("question", "")
        answer = item.get("correct_answer", "")
        trivia_list.append({"question": question, "answer": answer})
    return trivia_list


def generate_audio(text: str, filename: str) -> str:
    """Generate an MP3 audio file from text using gTTS.

    Parameters
    ----------
    text : str
        Text to convert into speech.
    filename : str
        Output MP3 file path.

    Returns
    -------
    str
        Path to the generated audio file.
    """
    tts = gTTS(text=text, lang="en")
    tts.save(filename)
    return filename


def generate_slide(text: str, filename: str, size: tuple[int, int] = (1280, 720)) -> str:
    """Create a slide image with the provided text.

    Parameters
    ----------
    text : str
        Text to draw on the image.
    filename : str
        Output image file path.
    size : tuple[int, int], optional
        Size of the generated image, by default (1280, 720).

    Returns
    -------
    str
        Path to the generated slide image.
    """
    img = Image.new("RGB", size, color=(255, 255, 255))
    draw = ImageDraw.Draw(img)
    try:
        font = ImageFont.truetype("arial.ttf", 40)
    except Exception:
        font = ImageFont.load_default()
    text_pos = (50, size[1] // 2 - 20)
    draw.text(text_pos, text, fill=(0, 0, 0), font=font)
    img.save(filename)
    return filename


def create_video(slide_files: list[str], audio_files: list[str], output: str) -> str:
    """Combine slides and audio files into a single MP4 video.

    Parameters
    ----------
    slide_files : list[str]
        Paths to image slides.
    audio_files : list[str]
        Corresponding audio file paths.
    output : str
        Output video file path.

    Returns
    -------
    str
        Path to the generated video.
    """
    clips = []
    for slide, audio in zip(slide_files, audio_files):
        img_clip = ImageClip(slide).set_duration(AudioFileClip(audio).duration)
        audio_clip = AudioFileClip(audio)
        clip = img_clip.set_audio(audio_clip)
        clips.append(clip)
    video = concatenate_videoclips(clips)
    video.write_videofile(output, codec="libx264", audio_codec="aac")
    return output


if __name__ == "__main__":
    # Example usage
    trivia = get_trivia_questions(3)
    slide_paths = []
    audio_paths = []

    os.makedirs("slides", exist_ok=True)
    os.makedirs("audio", exist_ok=True)

    for idx, item in enumerate(trivia, start=1):
        text = f"Q: {item['question']}\nA: {item['answer']}"
        slide_file = f"slides/slide_{idx}.png"
        audio_file = f"audio/audio_{idx}.mp3"
        generate_slide(text, slide_file)
        generate_audio(text, audio_file)
        slide_paths.append(slide_file)
        audio_paths.append(audio_file)

    create_video(slide_paths, audio_paths, "trivia_video.mp4")
    print("Video created: trivia_video.mp4")
