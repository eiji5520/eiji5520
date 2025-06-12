import argparse
from pathlib import Path


def main(path: str, caption: str):
    print('Post', path, caption)
    # Placeholder for pillow/moviepy processing and instagrapi upload

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Upload a post')
    parser.add_argument('path')
    parser.add_argument('caption')
    args = parser.parse_args()
    main(args.path, args.caption)
