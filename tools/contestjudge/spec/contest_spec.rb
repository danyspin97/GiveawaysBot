require 'rspec'
require_relative 'helpers/contest_helper'
require_relative '../lib/contestjudge/contest'

RSpec.describe ContestJudge::Contest do
  before do
    @subject = described_class.new(database: FakeDB.new)
    @subject.fetch
  end

  it 'should returns contests\' data' do
    expect(@subject.result['0']).not_to be_nil
  end

  context '#fetch' do
    it 'should fills contest with its participants' do
      expect(@subject.result['0'][:participants].size).to eq(1)
    end

    it 'should fills contest with its prizes' do
      expect(@subject.result['0'][:prizes].size).to eq(1)
    end
  end
end
