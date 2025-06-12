from fastapi import FastAPI, HTTPException
from fastapi.responses import JSONResponse, RedirectResponse
from pydantic import BaseModel
from pathlib import Path
import subprocess
import httpx
from jinja2 import Environment, FileSystemLoader

app = FastAPI()
BASE_DIR = Path(__file__).resolve().parent
env = Environment(loader=FileSystemLoader(BASE_DIR / 'templates'))

class Creds(BaseModel):
    username: str
    password: str

class PostData(BaseModel):
    path: str
    caption: str

@app.post('/auth')
async def auth(data: Creds):
    # Example BAN check via external API
    try:
        async with httpx.AsyncClient() as client:
            resp = await client.post('https://example.com/ban-check', json={'u': data.username})
            if resp.json().get('status') == 'BAN':
                raise HTTPException(status_code=403, detail='Banned')
    except Exception:
        pass
    return JSONResponse({'status': 'ok'})

@app.post('/run-login')
async def run_login(data: Creds):
    subprocess.run(['python', str(BASE_DIR / 'login.py'), data.username, data.password])
    return JSONResponse({'status': 'login-started'})

@app.post('/run-bot')
async def run_bot(payload: dict):
    subprocess.run(['python', str(BASE_DIR / 'bot.py')])
    return JSONResponse({'status': 'bot-started'})

@app.post('/run-post')
async def run_post(data: PostData):
    subprocess.run(['python', str(BASE_DIR / 'post.py'), data.path, data.caption])
    return RedirectResponse('/done')

@app.get('/done')
async def done():
    template = env.get_template('done.json')
    return JSONResponse(template.render())

if __name__ == '__main__':
    import uvicorn
    uvicorn.run(app, host='127.0.0.1', port=3030)
