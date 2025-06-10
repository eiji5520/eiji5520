import React, { useState } from 'react';
import {
  Card,
  CardContent,
  CardActions,
  Button,
  TextField,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Input,
} from '@mui/material';
import Datetime from 'react-datetime';
import 'react-datetime/css/react-datetime.css';

export default function PostSchedulerForm({ schedulePost }) {
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState('');
  const [caption, setCaption] = useState('');
  const [dateTime, setDateTime] = useState(null);
  const [type, setType] = useState('フィード');

  const handleFileChange = (e) => {
    const selected = e.target.files[0];
    setFile(selected);
    if (selected) {
      setPreview(URL.createObjectURL(selected));
    } else {
      setPreview('');
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    await schedulePost({ file, caption, dateTime, type });
    setFile(null);
    setPreview('');
    setCaption('');
    setDateTime(null);
    setType('フィード');
  };

  return (
    <Card className="max-w-md mx-auto">
      <form onSubmit={handleSubmit}>
        <CardContent>
          <FormControl fullWidth margin="normal">
            <InputLabel htmlFor="media">メディアパス</InputLabel>
            <Input id="media" type="file" onChange={handleFileChange} />
            {preview && (
              <img
                src={preview}
                alt="preview"
                className="mt-2 max-h-48 object-contain"
              />
            )}
          </FormControl>
          <FormControl fullWidth margin="normal">
            <TextField
              label="キャプション"
              multiline
              minRows={3}
              value={caption}
              onChange={(e) => setCaption(e.target.value)}
            />
          </FormControl>
          <FormControl fullWidth margin="normal">
            <InputLabel shrink>日時</InputLabel>
            <Datetime
              value={dateTime}
              onChange={(date) => setDateTime(date)}
              inputProps={{ placeholder: '日時を選択' }}
            />
          </FormControl>
          <FormControl fullWidth margin="normal">
            <InputLabel id="type-label">タイプ</InputLabel>
            <Select
              labelId="type-label"
              value={type}
              label="タイプ"
              onChange={(e) => setType(e.target.value)}
            >
              <MenuItem value="フィード">フィード</MenuItem>
              <MenuItem value="ストーリー">ストーリー</MenuItem>
              <MenuItem value="リール">リール</MenuItem>
            </Select>
          </FormControl>
        </CardContent>
        <CardActions>
          <Button type="submit" variant="contained" color="primary" fullWidth>
            予約する
          </Button>
        </CardActions>
      </form>
    </Card>
  );
}
